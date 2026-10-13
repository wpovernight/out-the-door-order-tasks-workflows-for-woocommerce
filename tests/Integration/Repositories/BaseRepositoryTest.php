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

	public function test_order_limit_offset_and_count(): void {
		$this->insert_statuses( array( 'c' => null, 'a' => null, 'd' => null, 'b' => null ) );

		$this->assertSame( array( 'b', 'c' ), $this->keys( $this->repository->order_by( 'status_key' )->limit( 2 )->offset( 1 )->get() ) );
		$this->assertSame( array( 'b', 'a' ), $this->keys( $this->repository->order_by( 'status_key', 'DESC' )->offset( 2 )->get() ) );
		$this->assertSame( 2, $this->repository->where( 'status_key', 'IN', array( 'a', 'b' ) )->order_by( 'status_key' )->limit( 1 )->count() );
		$this->assertSame( 'a', $this->repository->order_by( 'status_key' )->first()->status_key );
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

	public function test_find_helpers(): void {
		$ids = $this->insert_statuses( array( 'a' => '#111', 'b' => '#111', 'c' => '#222' ) );

		$this->assertSame( 'c', $this->repository->find_by( 'background', '#222' )->status_key );
		$this->assertEqualsCanonicalizing( array( 'a', 'b' ), $this->keys( $this->repository->find_all_by( 'background', '#111' ) ) );
		$this->assertEqualsCanonicalizing( array( 'a', 'c' ), $this->keys( $this->repository->find_many( array( $ids['a'], $ids['c'] ) ) ) );
		$this->assertEqualsCanonicalizing( array( 'b', 'c' ), $this->keys( $this->repository->find_all_by_in( 'status_key', array( 'b', 'c' ) ) ) );
		$this->assertNull( $this->repository->find( max( $ids ) + 100 ) );
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
