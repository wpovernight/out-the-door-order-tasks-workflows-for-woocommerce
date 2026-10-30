<?php

namespace WPO\OTD\Tests\Integration\Repositories;

use WPO\OTD\Models\CustomOrderStatus;
use WPO\OTD\Repositories\CustomOrderStatusRepository;
use WPO\OTD\Repositories\TaskFieldOptionRepository;
use WPO\OTD\Repositories\TaskFieldRepository;
use WPO\OTD\Tests\IntegrationTestCase;

/**
 * The query builder against MySQL, through the simplest concrete repository.
 */
class BaseRepositoryTest extends IntegrationTestCase {
	private CustomOrderStatusRepository $repository;

	public function set_up() {
		parent::set_up();

		$this->repository = new CustomOrderStatusRepository();
	}

	public function test_insert_find_update_and_delete_a_row(): void {
		$id = $this->repository->insert( array( 'status_key' => 'packed', 'label' => 'Packed', 'background' => '#fff' ) );

		$this->assertIsInt( $id );

		$status = $this->repository->find( $id );
		$this->assertInstanceOf( CustomOrderStatus::class, $status );
		$this->assertSame( 'Packed', $status->label );

		$this->assertSame( 1, $this->repository->where( 'id', $id )->update( array( 'label' => 'Boxed' ) ) );
		$this->assertSame( 0, $this->repository->where( 'id', $id )->update( array( 'label' => 'Boxed' ) ), 'Unchanged data affects no rows.' );
		$this->assertSame( 'Boxed', $this->repository->find( $id )->label );

		$this->assertSame( 1, $this->repository->delete( $id ) );
		$this->assertNull( $this->repository->find( $id ) );
	}

	public function test_save_inserts_then_updates_a_model(): void {
		$status = new CustomOrderStatus( array( 'label' => 'Packed' ) );

		$status->id = $this->repository->save( $status );
		$this->assertGreaterThan( 0, $status->id );

		$status->label = 'Boxed';
		$this->assertSame( 1, $this->repository->save( $status ) );
		$this->assertSame( 'Boxed', $this->repository->find( $status->id )->label );
	}

	public function test_a_rejected_insert_returns_false_and_exposes_the_error(): void {
		global $wpdb;

		$this->repository->insert( array( 'status_key' => 'packed', 'label' => 'Packed' ) );

		$wpdb->suppress_errors( true );
		$result = $this->repository->insert( array( 'status_key' => 'packed', 'label' => 'Duplicate' ) );
		$wpdb->suppress_errors( false );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'Duplicate entry', $this->repository->get_last_db_error() );
	}

	public function test_where_operators_against_real_rows(): void {
		$this->insert_statuses( array( 'alpha' => '#111', 'beta' => null, 'gamma' => '#333', 'delta' => '#444' ) );

		// No ORDER BY, so MySQL may return the rows in any order.
		$this->assertEqualsCanonicalizing( array( 'beta' ), $this->keys( $this->repository->where( 'background', 'IS', null )->get() ) );
		$this->assertEqualsCanonicalizing( array( 'alpha', 'gamma' ), $this->keys( $this->repository->where( 'status_key', 'IN', array( 'alpha', 'gamma', 'missing' ) )->get() ) );
		$this->assertEqualsCanonicalizing( array( 'beta', 'delta' ), $this->keys( $this->repository->where( 'status_key', 'NOT IN', array( 'alpha', 'gamma' ) )->get() ) );
		$this->assertEqualsCanonicalizing( array( 'alpha', 'gamma', 'delta' ), $this->keys( $this->repository->where( 'status_key', 'LIKE', '%a' )->where( 'status_key', '!=', 'beta' )->get() ) );
		$this->assertEqualsCanonicalizing( array( 'alpha', 'delta' ), $this->keys( $this->repository->where( 'status_key', 'alpha' )->where( 'status_key', '=', 'delta', 'OR' )->get() ) );
	}

	public function test_where_groups_against_real_rows(): void {
		$this->insert_statuses( array( 'alpha' => '#111', 'beta' => null, 'gamma' => '#333', 'delta' => '#444' ) );

		// Without the parentheses this would read "alpha OR beta AND ..." and also return alpha.
		$rows = $this->repository
			->where( 'background', 'IS NOT', null )
			->where_group( fn( $query ) => $query->where( 'status_key', 'alpha' )->where( 'status_key', '=', 'gamma', 'OR' ) )
			->where( 'status_key', '!=', 'alpha' )
			->get();

		$this->assertSame( array( 'gamma' ), $this->keys( $rows ) );
	}

	public function test_update_and_delete_refuse_an_or_and_leave_every_row_alone(): void {
		$ids = $this->insert_statuses( array( 'alpha' => '#111', 'beta' => '#222', 'gamma' => '#333' ) );

		// Read as an AND, these would match no row; with the second column overwriting the first, they would match beta.
		$wheres = fn() => $this->repository->where( 'id', $ids['alpha'] )->where( 'status_key', '=', 'beta', 'OR' );

		$this->assert_throws_invalid_argument( fn() => $wheres()->update( array( 'label' => 'Changed' ) ) );
		$this->assert_throws_invalid_argument( fn() => $wheres()->delete() );
		$this->assert_throws_invalid_argument( fn() => $this->repository->where( 'status_key', 'alpha' )->where( 'status_key', 'beta' )->delete() );

		$this->assertSame( array( 'Alpha', 'Beta', 'Gamma' ), array_map( static fn( $status ) => $status->label, $this->repository->order_by( 'id' )->get() ) );
		$this->assertSame( 1, $this->repository->where( 'id', $ids['alpha'] )->where( 'status_key', 'alpha' )->update( array( 'label' => 'Changed' ) ), 'The query builder is usable again.' );
	}

	public function test_writes_refuse_a_column_name_that_would_rewrite_the_statement(): void {
		$ids = $this->insert_statuses( array( 'alpha' => '#111', 'beta' => '#222' ) );

		// Each name passes the column check up to its alias, and $wpdb would write the rest into the SQL.
		$this->assert_throws_invalid_argument( fn() => $this->repository->where( 'id', $ids['alpha'] )->update( array( 'label AS x` = 1, `label' => 'Changed' ) ) );
		$this->assert_throws_invalid_argument( fn() => $this->repository->where( 'id AS x` = 0 OR `id', $ids['alpha'] )->update( array( 'label' => 'Changed' ) ) );
		$this->assert_throws_invalid_argument( fn() => $this->repository->where( 'id AS x` = 0 OR `id', $ids['alpha'] )->delete() );
		$this->assert_throws_invalid_argument( fn() => $this->repository->insert( array( 'status_key AS x`, `label' => 'gamma' ) ) );

		$this->assertSame( array( 'Alpha', 'Beta' ), array_map( static fn( $status ) => $status->label, $this->repository->order_by( 'id' )->get() ) );
	}

	public function test_a_bare_column_name_ends_at_its_last_character_not_before_a_trailing_newline(): void {
		$check = new \ReflectionMethod( $this->repository, 'validate_bare_column_names' );

		$check->invoke( $this->repository, array( 'label' ), 'set' );
		$this->assert_throws_invalid_argument( fn() => $check->invoke( $this->repository, array( "label\n" ), 'set' ) );
	}

	public function test_increment_changes_only_the_rows_its_conditions_still_match(): void {
		$ids = $this->insert_statuses( array( 'alpha' => '#111', 'beta' => null ) );

		$claim = fn() => $this->repository
			->where( 'id', $ids['alpha'] )
			->where( 'is_deleting', '<', 1 )
			->where_group( fn( $query ) => $query->where( 'background', '#111' )->where( 'background', 'IS', null, 'OR' ) )
			->increment( 'is_deleting', 1, array( 'label' => 'Claimed', 'background' => null ) );

		$this->assertSame( 1, $claim() );
		$this->assertSame( 0, $claim(), 'The counter is now 1, so the same statement no longer matches.' );

		$alpha = $this->repository->select( array( 'label', 'background', 'is_deleting' ) )->where( 'id', $ids['alpha'] )->get( true );
		$this->assertSame( array( array( 'label' => 'Claimed', 'background' => null, 'is_deleting' => '1' ) ), $alpha );
		$this->assertSame( 'Beta', $this->repository->find( $ids['beta'] )->label, 'Other rows are untouched.' );
	}

	public function test_update_matching_changes_only_the_rows_its_conditions_still_match(): void {
		$ids = $this->insert_statuses( array( 'alpha' => '#111', 'beta' => null, 'gamma' => '#333' ) );

		$take = fn() => $this->repository
			->where( 'id', '>=', $ids['alpha'] )
			->where( 'id', '<=', $ids['beta'] )
			->where_group( fn( $query ) => $query->where( 'background', '#111' )->where( 'background', 'IS', null, 'OR' ) )
			->update_matching( array( 'label' => 'Taken', 'background' => '#000' ) );

		$this->assertSame( 2, $take() );
		$this->assertSame( 0, $take(), 'Their background changed, so the same statement no longer matches.' );

		$this->assertSame(
			array( 'Taken', 'Taken', 'Gamma' ),
			array_map( static fn( $status ) => $status->label, $this->repository->where( 'id', 'IN', array_values( $ids ) )->order_by( 'id' )->get() )
		);
	}

	public function test_order_limit_offset_and_count(): void {
		$this->insert_statuses( array( 'c' => null, 'a' => null, 'd' => null, 'b' => null ) );

		$this->assertSame( array( 'b', 'c' ), $this->keys( $this->repository->order_by( 'status_key' )->limit( 2 )->offset( 1 )->get() ) );
		$this->assertSame( array( 'b', 'a' ), $this->keys( $this->repository->order_by( 'status_key', 'DESC' )->offset( 2 )->get() ) );
		$this->assertSame( 2, $this->repository->where( 'status_key', 'IN', array( 'a', 'b' ) )->order_by( 'status_key' )->limit( 1 )->count() );
		$this->assertSame( 'a', $this->repository->order_by( 'status_key' )->first()->status_key );
	}

	public function test_max_reads_the_highest_value_among_the_matching_rows(): void {
		$this->insert_statuses( array( 'a' => '#111', 'b' => '#333', 'c' => null ) );

		$this->assertSame( '#333', $this->repository->max( 'background' ) );
		$this->assertSame( '#111', $this->repository->where( 'status_key', 'IN', array( 'a', 'c' ) )->order_by( 'id' )->limit( 1 )->max( 'background' ) );
		$this->assertNull( $this->repository->where( 'status_key', 'c' )->max( 'background' ), 'NULL in every matching row.' );
		$this->assertNull( $this->repository->where( 'status_key', 'missing' )->max( 'background' ), 'No row matches.' );
		$this->assertSame( '', $this->repository->get_last_db_error() );
		$this->assertSame( 3, $this->repository->count(), 'The query is reset after.' );
	}

	public function test_max_by_reads_each_groups_highest_value_leaving_out_a_group_with_none(): void {
		$this->insert_statuses( array( 'a' => '#111', 'b' => '#333', 'c' => '#222', 'd' => null ) );
		$this->repository->where( 'status_key', 'b' )->update( array( 'label' => 'B' ) );
		$this->repository->where( 'status_key', 'c' )->update( array( 'label' => 'B' ) );

		$max = $this->repository->max_by( 'label', 'background' );
		ksort( $max );

		$this->assertSame( array( 'A' => '#111', 'B' => '#333' ), $max, 'D is NULL in every row, so it is left out.' );
		$this->assertSame(
			array( 'B' => '#222' ),
			$this->repository->where( 'status_key', 'IN', array( 'c', 'd' ) )->order_by( 'id' )->limit( 1 )->max_by( 'label', 'background' )
		);
		$this->assertSame( array(), $this->repository->where( 'status_key', 'missing' )->max_by( 'label', 'background' ) );
		$this->assertSame( 4, $this->repository->count(), 'The query is reset after.' );
	}

	public function test_max_and_max_by_tell_a_database_failure_apart(): void {
		$this->insert_statuses( array( 'a' => '#111' ) );
		$this->fail_queries( '/MAX\(/' );

		$this->assertNull( $this->repository->max( 'background' ) );
		$this->assertNotSame( '', $this->repository->get_last_db_error() );
		$this->assertNull( $this->repository->where( 'status_key', 'a' )->max_by( 'label', 'background' ) );
		$this->assertSame( 1, $this->repository->count(), 'The failed query left nothing behind.' );
	}

	public function test_max_and_max_by_refuse_a_column_the_table_does_not_have(): void {
		$this->assert_throws_invalid_argument( fn() => $this->repository->max( 'missing' ) );
		$this->assert_throws_invalid_argument( fn() => $this->repository->max_by( 'label', 'missing' ) );
		$this->assert_throws_invalid_argument( fn() => $this->repository->max_by( 'missing', 'label' ) );
	}

	/**
	 * @dataProvider not_bare_column_provider
	 *
	 * @param string $column
	 *
	 * @return void
	 */
	public function test_max_and_max_by_refuse_a_column_that_is_not_a_bare_name_and_leave_nothing_behind( string $column ): void {
		$this->insert_statuses( array( 'a' => '#111' ) );

		$this->assert_throws_invalid_argument( fn() => $this->repository->where( 'status_key', 'missing' )->max( $column ) );
		$this->assert_throws_invalid_argument( fn() => $this->repository->where( 'status_key', 'missing' )->max_by( $column, 'background' ) );
		$this->assert_throws_invalid_argument( fn() => $this->repository->where( 'status_key', 'missing' )->max_by( 'label', $column ) );
		$this->assertSame( 1, $this->repository->count(), 'The refused queries left no condition behind.' );
	}

	/**
	 * Columns that name a real one but would put more than a bare name into the SQL.
	 *
	 * @return array<string, array{string}>
	 */
	public static function not_bare_column_provider(): array {
		return array(
			'an alias'           => array( 'background AS b' ),
			'a qualified name'   => array( 'statuses.background' ),
			'a trailing clause'  => array( 'background) FROM x WHERE (1' ),
			'a trailing newline' => array( "background\n" ),
		);
	}

	public function test_select_returns_raw_rows_with_only_the_chosen_columns(): void {
		$this->insert_statuses( array( 'alpha' => '#111' ) );

		$this->assertSame(
			array( array( 'status_key' => 'alpha', 'name' => 'Alpha' ) ),
			$this->repository->select( array( 'status_key', 'label AS name' ) )->get( true )
		);
	}

	public function test_paginate_pages_by_id(): void {
		$this->insert_statuses( array( 'a' => null, 'b' => null, 'c' => null, 'd' => null, 'e' => null ) );

		$page = $this->repository->paginate( 2, 3 );

		$this->assertSame( array( 'e' ), $this->keys( $page->items() ) );
		$this->assertSame( 5, $page->total() );
		$this->assertSame( 3, $page->last_page() );
		$this->assertSame( 5, $page->from() );
		$this->assertSame( 5, $page->to() );

		$filtered = $this->repository->where( 'status_key', 'IN', array( 'b', 'd' ) )->paginate( 1, 2 );
		$this->assertSame( array( 'd' ), $this->keys( $filtered->items() ) );
		$this->assertSame( 2, $filtered->total() );
	}

	public function test_a_page_too_far_out_for_its_offset_to_fit_is_empty_with_the_real_total_and_resets_the_query(): void {
		$this->insert_statuses( array( 'a' => null, 'b' => null, 'c' => null ) );

		$page = $this->repository->where( 'status_key', 'IN', array( 'a', 'b' ) )->paginate( 2, PHP_INT_MAX );

		$this->assertSame( array(), $page->items() );
		$this->assertSame( 2, $page->total() );
		$this->assertSame( PHP_INT_MAX, $page->current_page() );
		$this->assertNull( $page->from() );
		$this->assertSame( 3, $this->repository->count(), 'The condition was not left behind.' );

		$last = $this->repository->paginate( 1, PHP_INT_MAX );
		$this->assertSame( array(), $last->items(), 'The last page whose offset still fits is read as usual.' );
		$this->assertSame( 3, $last->total() );
	}

	/**
	 * @dataProvider failing_page_query_provider
	 *
	 * @param string $pattern The query to fail.
	 *
	 * @return void
	 */
	public function test_try_paginate_tells_a_failed_read_apart_and_resets_the_query( string $pattern ): void {
		$this->insert_statuses( array( 'a' => null, 'b' => null ) );
		$this->fail_queries( $pattern );

		$this->assertNull( $this->repository->where( 'status_key', 'a' )->try_paginate( 1, 1 ) );

		$page = $this->repository->where( 'status_key', 'a' )->paginate( 1, 1 );

		$this->assertSame( array(), $page->items(), 'paginate() gives an empty page.' );
		$this->assertSame( 0, $page->total() );

		remove_all_filters( 'query' );

		$this->assertSame( 2, $this->repository->count(), 'The condition was not left behind.' );
	}

	/**
	 * Each query a page reads: the count, then the rows.
	 *
	 * @return array<string, array{string}>
	 */
	public static function failing_page_query_provider(): array {
		return array(
			'the count' => array( '/^SELECT COUNT\(\*\) FROM /' ),
			'the rows'  => array( '/^SELECT \* FROM .* LIMIT /' ),
		);
	}

	public function test_try_paginate_reads_a_page_as_paginate_does(): void {
		$this->insert_statuses( array( 'a' => null, 'b' => null, 'c' => null ) );

		$page = $this->repository->where( 'status_key', 'IN', array( 'b', 'c' ) )->try_paginate( 1, 2 );

		$this->assertSame( array( 'c' ), $this->keys( $page->items() ) );
		$this->assertSame( 2, $page->total() );
	}

	public function test_find_helpers(): void {
		$ids = $this->insert_statuses( array( 'a' => '#111', 'b' => '#111', 'c' => '#222' ) );

		$this->assertSame( 'c', $this->repository->find_by( 'background', '#222' )->status_key );
		$this->assertEqualsCanonicalizing( array( 'a', 'b' ), $this->keys( $this->repository->find_all_by( 'background', '#111' ) ) );
		$this->assertEqualsCanonicalizing( array( 'a', 'c' ), $this->keys( $this->repository->find_many( array( $ids['a'], $ids['c'] ) ) ) );
		$this->assertEqualsCanonicalizing( array( 'b', 'c' ), $this->keys( $this->repository->find_all_by_in( 'status_key', array( 'b', 'c' ) ) ) );
		$this->assertNull( $this->repository->find( max( $ids ) + 100 ) );
	}

	public function test_try_get_reads_rows_as_get_does_and_none_as_an_empty_list(): void {
		$this->insert_statuses( array( 'a' => '#111', 'b' => '#222' ) );

		$this->assertSame( array( 'b' ), $this->keys( $this->repository->where( 'background', '#222' )->try_get() ) );
		$this->assertSame( array( array( 'status_key' => 'a' ) ), $this->repository->select( array( 'status_key' ) )->where( 'background', '#111' )->try_get( true ) );
		$this->assertSame( array(), $this->repository->where( 'status_key', 'missing' )->try_get() );
	}

	public function test_try_get_tells_a_failed_read_apart_and_resets_the_query(): void {
		$this->insert_statuses( array( 'a' => '#111', 'b' => '#222' ) );
		$this->fail_queries( '/^SELECT \* FROM /' );

		$this->assertNull( $this->repository->where( 'status_key', 'a' )->try_get() );
		$this->assertSame( array(), $this->repository->where( 'status_key', 'a' )->get(), 'get() gives an empty list.' );

		remove_all_filters( 'query' );

		$this->assertSame( 2, $this->repository->count(), 'The condition was not left behind.' );
	}

	public function test_try_count_counts_as_count_does_and_tells_a_failed_count_apart(): void {
		$this->insert_statuses( array( 'a' => '#111', 'b' => '#111', 'c' => '#222' ) );

		$this->assertSame( 2, $this->repository->where( 'background', '#111' )->try_count() );
		$this->assertSame( 0, $this->repository->where( 'status_key', 'missing' )->try_count() );

		$this->fail_queries( '/^SELECT COUNT\(\*\) FROM /' );

		$this->assertNull( $this->repository->where( 'background', '#111' )->try_count() );
		$this->assertSame( 0, $this->repository->count(), 'count() gives 0.' );

		remove_all_filters( 'query' );

		$this->assertSame( 3, $this->repository->count(), 'The condition was not left behind.' );
	}

	public function test_try_find_tells_no_record_apart_from_a_failed_read(): void {
		$ids = $this->insert_statuses( array( 'a' => '#111' ) );

		$this->assertSame( 'a', $this->repository->try_find( $ids['a'] )->status_key );
		$this->assertNull( $this->repository->try_find( $ids['a'] + 100 ) );

		$this->fail_queries( '/^SELECT \* FROM /' );

		$this->assertFalse( $this->repository->try_find( $ids['a'] ) );
		$this->assertNull( $this->repository->find( $ids['a'] ), 'find() gives null.' );
	}

	public function test_the_last_error_number_names_a_failed_query_without_its_text_until_the_next_one(): void {
		$this->insert_statuses( array( 'a' => '#111' ) );
		$this->fail_queries( '/^SELECT \* FROM /' );

		$this->assertNull( $this->repository->where( 'status_key', 'a' )->try_get() );
		$this->assertSame( 1146, $this->repository->get_last_db_error_number(), 'The forced failure reads a table that does not exist.' );

		remove_all_filters( 'query' );

		$this->assertSame( 1, $this->repository->count() );
		$this->assertSame( 0, $this->repository->get_last_db_error_number(), 'A statement that succeeds has no error.' );
	}

	public function test_a_failed_quiet_query_leaves_neither_its_text_nor_its_error_in_wpdb(): void {
		global $wpdb;

		$repository = new QuietStatusRepository();
		$repository->insert( array( 'status_key' => 'packed', 'label' => 'Packed' ) );

		$debug_errors = count( $GLOBALS['EZSQL_ERROR'] ?? array() );
		$suppressing  = $wpdb->suppress_errors( false );

		try {
			$result = $repository->run_quietly( fn() => $repository->insert( array( 'status_key' => 'packed', 'label' => 'whsec-quiet-0001' ) ), 'the database refused the write' );

			$this->assertFalse( $wpdb->suppress_errors( false ), 'Errors are shown again after, as they were before.' );
		} finally {
			$wpdb->suppress_errors( $suppressing );
		}

		$this->assertFalse( $result );
		$this->assertSame( 'the database refused the write (MySQL error 1062)', $repository->get_last_db_error() );
		$this->assertNull( $wpdb->last_query );
		$this->assertCount( $debug_errors, $GLOBALS['EZSQL_ERROR'] ?? array(), 'The errors kept for debugging tools lose the one it added.' );
	}

	public function test_a_quiet_query_that_succeeds_gives_its_result_and_leaves_no_text_behind(): void {
		global $wpdb;

		$repository = new QuietStatusRepository();

		$id = $repository->run_quietly( fn() => $repository->insert( array( 'status_key' => 'packed', 'label' => 'whsec-quiet-0002' ) ), 'the database refused the write' );

		$this->assertIsInt( $id );
		$this->assertSame( 'whsec-quiet-0002', $repository->find( $id )->label );

		$repository->run_quietly( fn() => $repository->insert( array( 'status_key' => 'boxed', 'label' => 'whsec-quiet-0003' ) ), 'the database refused the write' );

		$this->assertSame( '', $repository->get_last_db_error() );
		$this->assertNull( $wpdb->last_query );
	}

	public function test_a_quiet_query_that_throws_is_thrown_on_as_it_is_with_only_its_class_in_wpdb(): void {
		$repository = new QuietStatusRepository();
		$thrown     = new \RuntimeException( 'Lock wait timeout near whsec-quiet-0004' );

		try {
			$repository->run_quietly(
				static function () use ( $thrown ): never {
					throw $thrown;
				},
				'the database refused the write'
			);
			$this->fail( 'The exception must propagate.' );
		} catch ( \RuntimeException $exception ) {
			$this->assertSame( $thrown, $exception );
		}

		$this->assertSame( 'the database refused the write (RuntimeException)', $repository->get_last_db_error() );
	}

	public function test_a_quiet_query_refused_before_it_ran_gives_the_cause_alone(): void {
		global $wpdb;

		$repository  = new QuietStatusRepository();
		$suppressing = $wpdb->suppress_errors( true );

		// An earlier statement leaves its error number on the connection.
		$wpdb->query( 'SELECT * FROM `wpo_otd_no_such_table`' );
		$wpdb->suppress_errors( $suppressing );

		$this->assertSame( 1146, $repository->get_last_db_error_number() );
		$this->assertFalse( $repository->run_quietly( static fn(): bool => false, 'the database refused the write' ) );
		$this->assertSame( 'the database refused the write', $repository->get_last_db_error(), 'No number of the earlier statement.' );
	}

	public function test_a_quiet_query_that_fails_inside_a_callback_that_goes_on_is_cleaned_up_all_the_same(): void {
		global $wpdb;

		$repository = new QuietStatusRepository();
		$repository->insert( array( 'status_key' => 'packed', 'label' => 'Packed' ) );

		$debug_errors = count( $GLOBALS['EZSQL_ERROR'] ?? array() );

		$result = $repository->run_quietly(
			static function () use ( $repository ): int {
				$repository->insert( array( 'status_key' => 'packed', 'label' => 'whsec-quiet-0007' ) );

				return 1;
			},
			'the database refused the write'
		);

		$this->assertSame( 1, $result, 'What the callback returned.' );
		$this->assertSame( 'the database refused the write (MySQL error 1062)', $repository->get_last_db_error(), 'The error the query left, replaced.' );
		$this->assertCount( $debug_errors, $GLOBALS['EZSQL_ERROR'] ?? array(), 'The errors kept for debugging tools lose the one it added.' );
		$this->assertNull( $wpdb->last_query );
		$this->assertSame( '', (string) $wpdb->func_call, 'Its last call, which quotes the query, is gone too.' );
	}

	public function test_an_error_left_before_a_quiet_callback_that_runs_no_query_is_left_alone(): void {
		global $wpdb;

		$repository = new QuietStatusRepository();
		$wpdb->last_error = 'an earlier error';

		$this->assertSame( 2, $repository->run_quietly( static fn(): int => 2, 'the database refused the write' ) );
		$this->assertSame( 'an earlier error', $repository->get_last_db_error() );

		$wpdb->last_error = '';
	}

	public function test_the_queries_saved_for_debugging_tools_during_a_quiet_query_keep_their_place_but_not_their_text(): void {
		global $wpdb;

		$saved        = $wpdb->queries;
		$wpdb->queries = array( array( 'SELECT 1', 0.1, 'earlier' ) );

		try {
			( new QuietStatusRepository() )->run_quietly(
				static function () use ( $wpdb ): int {
					$wpdb->queries[] = array( "INSERT 'whsec-quiet-0005'", 0.2, 'caller' );
					$wpdb->queries[] = array(
						'query'   => "INSERT 'whsec-quiet-0006'",
						'elapsed' => 0.3,
					);

					return 1;
				},
				'the database refused the write'
			);

			$this->assertSame(
				array(
					array( 'SELECT 1', 0.1, 'earlier' ),
					array( '[a query holding a secret]', 0.2, 'caller' ),
					array(
						'query'   => '[a query holding a secret]',
						'elapsed' => 0.3,
					),
				),
				$wpdb->queries
			);
		} finally {
			$wpdb->queries = $saved;
		}
	}

	public function test_where_on_a_joined_tables_column(): void {
		$fields = ( new TaskFieldRepository() )->get_table_full_name();

		// is_protected exists only on the fields table, so this used to be rejected as an unknown column.
		$options = ( new TaskFieldOptionRepository() )
			->select( array( 'o.*' ) )
			->alias( 'o' )
			->join( "{$fields} AS f", 'f.id', '=', 'o.field_id' )
			->where( 'f.is_protected', 1 )
			->order_by( 'o.position' )
			->get();

		$this->assertSame(
			array( 'not_started', 'in_progress', 'done' ),
			array_map( static fn( $option ) => $option->slug, $options ),
			'Only the protected status field has protected options.'
		);
	}

	public function test_a_query_that_throws_does_not_poison_the_next_one(): void {
		$this->insert_statuses( array( 'alpha' => null ) );

		// Only the aliased query throws; a leftover alias would make the next query throw too.
		$this->throw_on_queries( '/ AS s WHERE /', new \RuntimeException( 'Gone away' ) );

		try {
			$this->repository->alias( 's' )->where( 's.status_key', 'alpha' )->get();
			$this->fail( 'The exception must propagate.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'Gone away', $e->getMessage() );
		}

		$this->assertSame( array( 'alpha' ), $this->keys( $this->repository->get() ) );
	}

	public function test_find_by_slug_on_a_table_with_slugs(): void {
		$option = ( new TaskFieldOptionRepository() )->find_by_slug( 'in_progress' );

		$this->assertSame( $this->status_option_id( 'in_progress' ), $option->id );
	}

	public function test_delete_raw_and_execute_raw(): void {
		$this->insert_statuses( array( 'a' => null, 'b' => null, 'c' => null ) );

		$this->assertSame( 2, $this->repository->where( 'status_key', 'IN', array( 'a', 'b' ) )->delete_raw() );
		$this->assertSame(
			1,
			$this->repository->execute_raw(
				"UPDATE {$this->repository->get_table_full_name()} SET label = %s WHERE status_key = %s",
				array( 'Renamed', 'c' )
			)
		);
		$this->assertSame( array( 'Renamed' ), array_column( $this->repository->get( true ), 'label' ) );
	}

	public function test_a_transaction_commits_its_writes(): void {
		$id = $this->repository->transaction( fn( $repository ) => $repository->insert( array( 'status_key' => 'kept', 'label' => 'Kept' ) ) );

		$this->assertSame( 'kept', $this->repository->find( $id )->status_key );
	}

	public function test_a_locking_read_inside_a_transaction_returns_the_row(): void {
		$id = $this->repository->insert( array( 'status_key' => 'locked', 'label' => 'Locked' ) );

		$status = $this->repository->transaction( fn( $repository ) => $repository->where( 'id', $id )->lock_for_update()->first() );

		$this->assertInstanceOf( CustomOrderStatus::class, $status );
		$this->assertSame( 'locked', $status->status_key );
	}

	public function test_a_transaction_returning_false_rolls_its_writes_back(): void {
		$this->insert_statuses( array( 'before' => null ) );

		$result = $this->repository->transaction(
			static function ( $repository ) {
				$repository->insert( array( 'status_key' => 'discarded', 'label' => 'Discarded' ) );

				return false;
			}
		);

		$this->assertFalse( $result );
		$this->assertSame( array( 'before' ), $this->keys( $this->repository->get() ), 'Only the write inside the transaction is undone.' );
	}

	public function test_a_transaction_the_database_cannot_commit_rolls_its_writes_back_and_fails(): void {
		$this->insert_statuses( array( 'before' => null ) );

		// The COMMIT fails, as when the connection drops before it reaches the server.
		$this->fail_queries( '/^COMMIT$/' );

		$result = $this->repository->transaction( static fn( $repository ) => $repository->insert( array( 'status_key' => 'discarded', 'label' => 'Discarded' ) ) );

		$this->assertFalse( $result );
		$this->assertNotSame( '', $this->repository->get_last_db_error(), 'The commit error is kept.' );
		$this->assertSame( array( 'before' ), $this->keys( $this->repository->get() ) );
	}

	public function test_a_transaction_can_see_which_connection_wpdb_is_on(): void {
		global $wpdb;

		// A transaction compares this before and after its commit, so a reconnect is noticed only if it can be read.
		$this->assertInstanceOf( \mysqli::class, $wpdb->dbh ?? null );
	}

	public function test_a_transaction_that_throws_rolls_back_and_rethrows(): void {
		try {
			$this->repository->transaction(
				static function ( $repository ) {
					$repository->insert( array( 'status_key' => 'discarded', 'label' => 'Discarded' ) );

					throw new \RuntimeException( 'Boom' );
				}
			);
			$this->fail( 'The exception must propagate.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'Boom', $e->getMessage() );
		}

		$this->assertSame( 0, $this->repository->count() );
	}

	/**
	 * Insert statuses keyed by status_key, with the given background.
	 *
	 * @param array<string, string|null> $statuses
	 *
	 * @return array<string, int> Inserted IDs by status_key.
	 */
	private function insert_statuses( array $statuses ): array {
		$ids = array();

		foreach ( $statuses as $key => $background ) {
			$ids[ $key ] = $this->repository->insert(
				array(
					'status_key' => $key,
					'label'      => ucfirst( $key ),
					'background' => $background,
				)
			);
		}

		return $ids;
	}

	/**
	 * Assert that a callback throws InvalidArgumentException.
	 *
	 * @param callable $callback
	 *
	 * @return void
	 */
	private function assert_throws_invalid_argument( callable $callback ): void {
		try {
			$callback();
		} catch ( \InvalidArgumentException $e ) {
			$this->addToAssertionCount( 1 );

			return;
		}

		$this->fail( 'Expected InvalidArgumentException to be thrown.' );
	}

	/**
	 * Get the status keys of a list of models.
	 *
	 * @param CustomOrderStatus[] $statuses
	 *
	 * @return string[]
	 */
	private function keys( array $statuses ): array {
		return array_map( static fn( $status ) => $status->status_key, array_values( $statuses ) );
	}
}

/**
 * Makes quietly() callable.
 */
class QuietStatusRepository extends CustomOrderStatusRepository {
	public function run_quietly( callable $queries, string $cause ): mixed {
		return $this->quietly( $queries, $cause );
	}
}
