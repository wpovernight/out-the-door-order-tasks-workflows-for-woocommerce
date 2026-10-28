<?php

namespace WPO\OTD\Tests\Unit\Repositories;

use DateTime;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use WPO\OTD\Models\BaseModel;
use WPO\OTD\Repositories\BaseRepository;
use WPO\OTD\Tests\TestCase;
use WPO\OTD\Utilities\Paginator;

/**
 * How the query builder assembles SQL, validates input and talks to $wpdb.
 *
 * $wpdb is a mock whose prepare() returns the template followed by its JSON-encoded bindings,
 * so each assertion shows both the placeholders chosen and the values bound to them. Running the
 * SQL for real is covered in Integration\Repositories\BaseRepositoryTest.
 */
class BaseRepositoryTest extends TestCase {
	private const TABLE = 'wp_wpo_otd_gadgets';

	/**
	 * @var Mockery\MockInterface
	 */
	private $wpdb;

	private GadgetRepository $repository;

	protected function setUp(): void {
		parent::setUp();

		$this->set_static_property( BaseRepository::class, 'column_names', null );
		$this->set_static_property( BaseRepository::class, 'cache', array() );

		$this->wpdb             = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix     = 'wp_';
		$this->wpdb->last_error = '';
		$this->wpdb->insert_id  = 0;

		$this->wpdb->shouldReceive( 'get_col' )
			->with( 'DESCRIBE ' . self::TABLE )
			->andReturn( array( 'id', 'name', 'price', 'status', 'created_at', 'updated_at' ) )
			->byDefault();

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( static fn( $query, ...$args ) => $query . ' -- ' . wp_json_encode_stub( $args ) )
			->byDefault();

		$GLOBALS['wpdb'] = $this->wpdb;

		$this->repository = new GadgetRepository();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		$this->set_static_property( BaseRepository::class, 'column_names', null );
		$this->set_static_property( BaseRepository::class, 'cache', array() );

		parent::tearDown();
	}

	/** ================================
	 *   SELECT assembly
	 *  ================================ */

	public function test_a_bare_query_selects_everything_without_preparing(): void {
		$this->wpdb->shouldNotReceive( 'prepare' );

		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query() );
	}

	public function test_where_picks_placeholders_by_value_type_and_chains_logical_operators(): void {
		$query = $this->repository
			->where( 'name', '=', 'Lamp' )
			->where( 'price', '>', 10 )
			->where( 'status', 'LIKE', 'a%', 'or' )
			->where( 'price', '<=', 9.5 )
			->get_query();

		$this->assertSame(
			'SELECT * FROM ' . self::TABLE . ' WHERE name = %s  AND price > %d  OR status LIKE %s  AND price <= %f -- ["Lamp",10,"a%",9.5]',
			$query
		);
	}

	public function test_the_two_argument_form_binds_the_value_as_a_string(): void {
		// The value travels through the string-typed $operator parameter, so 4 arrives as "4".
		$this->assertSame(
			'SELECT * FROM ' . self::TABLE . ' WHERE id = %s -- ["4"]',
			$this->repository->where( 'id', 4 )->get_query()
		);
	}

	public function test_operators_are_case_sensitive(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid operator provided.' );

		$this->repository->where( 'status', 'like', 'a%' );
	}

	public function test_column_names_are_lowercased(): void {
		$this->assertSame(
			'SELECT * FROM ' . self::TABLE . ' WHERE name = %s -- ["Lamp"]',
			$this->repository->where( 'NAME', 'Lamp' )->get_query()
		);
	}

	public function test_in_clauses_get_one_placeholder_per_value(): void {
		$query = $this->repository
			->where( 'id', 'IN', array( 1, 2, 3 ) )
			->where( 'status', 'NOT IN', array( 'trash' ) )
			->get_query();

		$this->assertSame(
			'SELECT * FROM ' . self::TABLE . ' WHERE id IN (%s,%s,%s)  AND status NOT IN (%s) -- [1,2,3,"trash"]',
			$query
		);
	}

	public function test_null_comparisons_become_is_null_without_a_binding(): void {
		$this->wpdb->shouldNotReceive( 'prepare' );

		$query = $this->repository
			->where( 'status', 'IS', null )
			->where( 'name', 'IS NOT', null, 'or' )
			->get_query();

		$this->assertSame( 'SELECT * FROM ' . self::TABLE . ' WHERE status IS NULL  OR name IS NOT NULL', $query );
	}

	public function test_null_with_a_comparison_operator_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->repository->where( 'status', '=', null );
	}

	public function test_raw_where_clauses_are_inserted_verbatim(): void {
		$query = $this->repository
			->where( 'name', 'Lamp' )
			->where_raw_public( 'price * 2 > 10', 'or' )
			->get_query();

		$this->assertSame( 'SELECT * FROM ' . self::TABLE . ' WHERE name = %s  OR price * 2 > 10 -- ["Lamp"]', $query );
	}

	/**
	 * @dataProvider invalid_where_provider
	 */
	public function test_invalid_where_arguments_are_rejected( string $column, string $operator, $value, string $logical ): void {
		$this->expectException( InvalidArgumentException::class );

		$this->repository->where( $column, $operator, $value, $logical );
	}

	public function invalid_where_provider(): array {
		return array(
			'unknown column'           => array( 'colour', '=', 'red', 'AND' ),
			'unsupported operator'     => array( 'name', '<>', 'x', 'AND' ),
			'unsupported logical join' => array( 'name', '=', 'x', 'XOR' ),
		);
	}

	public function test_raw_where_rejects_an_unsupported_logical_operator(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->repository->where_raw_public( '1 = 1', 'XOR' );
	}

	public function test_a_where_group_is_parenthesised_and_binds_its_values_in_place(): void {
		$query = $this->repository
			->where( 'name', 'Lamp' )
			->where_group(
				fn( $query ) => $query
					->where( 'status', 'active' )
					->where_group( fn( $query ) => $query->where( 'status', 'draft' )->where( 'price', '<=', 10 ), 'or' )
			)
			->where( 'id', '>', 3 )
			->get_query();

		$this->assertSame(
			'SELECT * FROM ' . self::TABLE . ' WHERE name = %s  AND ( status = %s  OR ( status = %s  AND price <= %d ) )  AND id > %d -- ["Lamp","active","draft",10,3]',
			$query
		);
	}

	public function test_a_where_group_can_come_first_and_hold_raw_clauses(): void {
		$query = $this->repository
			->where_group( fn( $query ) => $query->where_raw_public( 'price * 2 > 10' )->where( 'name', 'IS', null, 'OR' ) )
			->get_query();

		$this->assertSame( 'SELECT * FROM ' . self::TABLE . ' WHERE ( price * 2 > 10  OR name IS NULL )', $query );
	}

	public function test_an_empty_where_group_adds_nothing(): void {
		$this->wpdb->shouldNotReceive( 'prepare' );

		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->where_group( static fn() => null )->get_query() );
	}

	public function test_a_where_group_rejects_an_unsupported_logical_operator(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->repository->where_group( static fn() => null, 'XOR' );
	}

	public function test_a_where_group_whose_callback_throws_discards_the_query_being_built(): void {
		$this->repository->where( 'name', 'Lamp' );

		$this->assert_throws(
			RuntimeException::class,
			fn() => $this->repository->where_group(
				static function ( $query ) {
					$query->where( 'status', 'active' );

					throw new RuntimeException( 'Gone away' );
				}
			)
		);

		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query() );
	}

	public function test_a_where_group_with_a_rejected_clause_discards_the_query_being_built(): void {
		$this->repository->where( 'name', 'Lamp' );

		$this->assert_throws( InvalidArgumentException::class, fn() => $this->repository->where_group( fn( $query ) => $query->where( 'colour', 'red' ) ) );

		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query() );
	}

	public function test_update_and_delete_reject_a_where_group(): void {
		$group = static fn( $query ) => $query->where( 'id', 4 );

		$this->assert_throws( InvalidArgumentException::class, fn() => $this->repository->where_group( $group )->update( array( 'name' => 'x' ) ) );
		$this->assert_throws( InvalidArgumentException::class, fn() => $this->repository->where_group( $group )->delete() );
	}

	public function test_select_accepts_aliases_qualified_names_and_star(): void {
		$query = $this->repository->select( array( 'g.name AS label', 'price', '*' ) )->get_query();

		$this->assertSame( 'SELECT g.name AS label, price, * FROM ' . self::TABLE, $query );
	}

	public function test_select_rejects_unknown_columns(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Column colour does not exist in the table.' );

		$this->repository->select( array( 'name', 'colour' ) );
	}

	public function test_order_limit_and_offset(): void {
		$query = $this->repository->order_by( 'name', 'desc' )->limit( 5 )->offset( 10 )->get_query();

		$this->assertSame( 'SELECT * FROM ' . self::TABLE . ' ORDER BY name DESC LIMIT 5 OFFSET 10', $query );
	}

	public function test_an_offset_without_a_limit_uses_the_largest_limit(): void {
		$this->assertSame(
			'SELECT * FROM ' . self::TABLE . ' LIMIT 18446744073709551615 OFFSET 3',
			$this->repository->offset( 3 )->get_query()
		);
	}

	public function test_a_locking_read_ends_with_for_update(): void {
		$this->assertSame(
			'SELECT * FROM ' . self::TABLE . ' WHERE id = %s ORDER BY name DESC LIMIT 1 OFFSET 2 FOR UPDATE -- ["5"]',
			$this->repository->where( 'id', 5 )->order_by( 'name', 'desc' )->limit( 1 )->offset( 2 )->lock_for_update()->get_query()
		);
	}

	public function test_the_lock_is_forgotten_when_the_query_resets(): void {
		$this->repository->lock_for_update()->get_query( true );

		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query() );
	}

	public function test_first_can_lock_the_row_it_reads(): void {
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'SELECT * FROM ' . self::TABLE . ' WHERE id = %s LIMIT 1 FOR UPDATE -- ["5"]', ARRAY_A )
			->andReturn( array( array( 'id' => '5', 'name' => 'Lamp' ) ) );

		$this->assertSame( 5, $this->repository->where( 'id', 5 )->lock_for_update()->first()->id );
		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query(), 'The lock does not outlive the read.' );
	}

	public function test_negative_limits_and_offsets_are_made_absolute(): void {
		$this->assertSame(
			'SELECT * FROM ' . self::TABLE . ' LIMIT 2 OFFSET 4',
			$this->repository->limit( -2 )->offset( -4 )->get_query()
		);
	}

	public function test_raw_order_by(): void {
		$this->assertSame(
			'SELECT * FROM ' . self::TABLE . ' ORDER BY CAST(price AS DECIMAL) ASC',
			$this->repository->order_by_raw_public( 'CAST(price AS DECIMAL) ASC' )->get_query()
		);
	}

	public function test_order_by_rejects_bad_directions_and_columns(): void {
		try {
			$this->repository->order_by( 'name', 'sideways' );
			$this->fail( 'Expected an invalid direction to be rejected.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertSame( 'Invalid sort direction.', $e->getMessage() );
		}

		$this->expectException( InvalidArgumentException::class );
		$this->repository->order_by( 'colour' );
	}

	public function test_joins_with_a_column_comparison_or_a_full_condition(): void {
		$query = $this->repository
			->alias( 'g' )
			->join( 'wp_parts AS p', 'g.id', '=', 'p.gadget_id', 'left' )
			->join( 'wp_tags AS t', 't.gadget_id = g.id' )
			->where( 'g.name', 'Lamp' )
			->get_query();

		$this->assertSame(
			'SELECT * FROM ' . self::TABLE . ' AS g LEFT JOIN wp_parts AS p ON g.id = p.gadget_id INNER JOIN wp_tags AS t ON t.gadget_id = g.id  WHERE g.name = %s -- ["Lamp"]',
			$query
		);
	}

	/**
	 * @dataProvider invalid_join_provider
	 */
	public function test_invalid_joins_are_rejected( ?string $operator, ?string $second, string $type ): void {
		$this->expectException( InvalidArgumentException::class );

		$this->repository->join( 'wp_parts AS p', 'g.id', $operator, $second, $type );
	}

	public function invalid_join_provider(): array {
		return array(
			'unknown type'          => array( '=', 'p.gadget_id', 'OUTER' ),
			'operator, no column'   => array( '=', null, 'INNER' ),
			'column, no operator'   => array( null, 'p.gadget_id', 'INNER' ),
			'unsupported operator'  => array( '<>', 'p.gadget_id', 'INNER' ),
		);
	}

	public function test_the_alias_only_applies_when_asked_for(): void {
		$this->repository->alias( 'g' );

		$this->assertSame( self::TABLE, $this->repository->get_table_full_name() );
		$this->assertSame( self::TABLE . ' AS g', $this->repository->get_table_full_name( true ) );
	}

	public function test_a_column_qualified_with_a_join_alias_is_checked_against_the_joined_table(): void {
		$this->expect_parts_described_once();

		$query = $this->repository
			->alias( 'g' )
			->join( 'wp_parts AS p', 'g.id', '=', 'p.gadget_id' )
			->where( 'p.sku', 'X1' )
			->order_by( 'p.sku' )
			->get_query();

		$this->assertSame(
			'SELECT * FROM ' . self::TABLE . ' AS g INNER JOIN wp_parts AS p ON g.id = p.gadget_id  WHERE p.sku = %s ORDER BY p.sku ASC -- ["X1"]',
			$query
		);
	}

	public function test_a_column_the_joined_table_lacks_is_rejected_even_when_this_table_has_it(): void {
		$this->expect_parts_described_once();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Column name does not exist in the table.' );

		$this->repository->join( 'wp_parts AS p', 'p.gadget_id = id' )->where( 'p.name', 'Lamp' );
	}

	/**
	 * @dataProvider join_table_provider
	 */
	public function test_every_form_of_join_table_records_what_the_query_calls_it( string $table, string $qualifier ): void {
		$this->expect_parts_described_once();

		$query = $this->repository->join( $table, "{$qualifier}.gadget_id = id" )->where( "{$qualifier}.sku", 'X1' )->get_query();

		$this->assertStringContainsString( "WHERE {$qualifier}.sku = %s", $query );
	}

	public function join_table_provider(): array {
		return array(
			'with AS'        => array( 'wp_parts AS p', 'p' ),
			'lowercase as'   => array( 'wp_parts as p', 'p' ),
			'without AS'     => array( 'wp_parts p', 'p' ),
			'backticks'      => array( '`wp_parts` AS `p`', 'p' ),
			'no alias'       => array( 'wp_parts', 'wp_parts' ),
			'padded'         => array( '  wp_parts AS p  ', 'p' ),
		);
	}

	public function test_a_qualifier_that_names_no_recorded_join_is_checked_against_this_table(): void {
		$this->wpdb->shouldNotReceive( 'get_col' )->with( 'DESCRIBE wp_parts' );

		// A subquery is not recorded, so "p.name" falls back to this repository's columns.
		$query = $this->repository
			->join( '(SELECT gadget_id FROM wp_parts) AS p', 'p.gadget_id = id' )
			->where( 'p.name', 'Lamp' )
			->get_query();

		$this->assertStringContainsString( 'WHERE p.name = %s', $query );
	}

	public function test_joined_tables_are_forgotten_when_the_query_resets(): void {
		$this->wpdb->shouldNotReceive( 'get_col' )->with( 'DESCRIBE wp_parts' );

		$this->repository->join( 'wp_parts AS p', 'p.gadget_id = id' )->get_query( true );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Column sku does not exist in the table.' );

		$this->repository->where( 'p.sku', 'X1' );
	}

	/** ================================
	 *   A failure discards the half-built query
	 *  ================================ */

	public function test_a_rejected_where_discards_the_query_being_built(): void {
		$this->repository->alias( 'g' )->join( 'wp_tags AS t', 't.gadget_id = g.id' )->where( 'name', 'Lamp' );

		$this->assert_throws( InvalidArgumentException::class, fn() => $this->repository->where( 'colour', 'red' ) );

		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query(), 'The repository is shared; leftovers would leak into the next query.' );
	}

	public function test_a_rejected_join_order_or_update_discards_the_query_being_built(): void {
		$attempts = array(
			fn() => $this->repository->where( 'name', 'Lamp' )->join( 'wp_parts AS p', 'p.gadget_id = id', null, null, 'OUTER' ),
			fn() => $this->repository->where( 'name', 'Lamp' )->order_by( 'name', 'sideways' ),
			fn() => $this->repository->where( 'id', '>', 4 )->update( array( 'name' => 'x' ) ),
		);

		foreach ( $attempts as $attempt ) {
			$this->assert_throws( \Throwable::class, $attempt );
			$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query() );
		}
	}

	public function test_a_column_lookup_that_throws_discards_the_query_being_built(): void {
		$this->wpdb->shouldReceive( 'get_col' )->with( 'DESCRIBE wp_parts' )->andThrow( new RuntimeException( 'Gone away' ) );

		$this->assert_throws(
			RuntimeException::class,
			fn() => $this->repository->where( 'name', 'Lamp' )->join( 'wp_parts AS p', 'p.gadget_id = id' )->where( 'p.sku', 'X1' )
		);

		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query() );
	}

	/**
	 * @dataProvider failing_query_provider
	 */
	public function test_a_query_that_throws_discards_the_builder_even_when_asked_to_keep_it( string $wpdb_method, callable $run ): void {
		$this->wpdb->shouldReceive( $wpdb_method )->andThrow( new RuntimeException( 'Gone away' ) );

		$this->repository->alias( 'g' )->where( 'name', 'Lamp' );

		$this->assert_throws( RuntimeException::class, fn() => $run( $this->repository ) );
		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query() );
	}

	public function failing_query_provider(): array {
		return array(
			'get, keeping the builder'   => array( 'get_results', static fn( $repository ) => $repository->get( false, false ) ),
			'count, keeping the builder' => array( 'get_var', static fn( $repository ) => $repository->count( false ) ),
			'paginate'                   => array( 'get_var', static fn( $repository ) => $repository->paginate( 5 ) ),
			'delete_raw'                 => array( 'query', static fn( $repository ) => $repository->delete_raw() ),
		);
	}

	public function test_get_query_keeps_the_builder_unless_asked_to_reset(): void {
		$this->repository->where( 'name', 'Lamp' );

		$kept = $this->repository->get_query();
		$this->assertSame( $kept, $this->repository->get_query( true ) );
		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query() );
	}

	public function test_table_columns_are_described_once_per_table(): void {
		$this->wpdb->shouldReceive( 'get_col' )
			->once()
			->with( 'DESCRIBE ' . self::TABLE )
			->andReturn( array( 'id', 'name' ) );

		$this->repository->where( 'name', 'a' )->get_query( true );
		( new GadgetRepository() )->where( 'name', 'b' )->get_query( true );
	}

	/** ================================
	 *   Reads
	 *  ================================ */

	public function test_get_maps_rows_to_models_and_resets_the_builder(): void {
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'SELECT * FROM ' . self::TABLE . ' WHERE name = %s -- ["Lamp"]', ARRAY_A )
			->andReturn( array( array( 'id' => '1', 'name' => 'Lamp' ) ) );

		$results = $this->repository->where( 'name', 'Lamp' )->get();

		$this->assertCount( 1, $results );
		$this->assertInstanceOf( Gadget::class, $results[0] );
		$this->assertSame( 1, $results[0]->id );
		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query() );
	}

	public function test_get_can_return_raw_rows_and_keep_the_builder(): void {
		$rows = array( array( 'id' => '1', 'name' => 'Lamp' ) );
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( $rows );

		$this->assertSame( $rows, $this->repository->where( 'name', 'Lamp' )->get( true, false ) );
		$this->assertStringContainsString( 'WHERE name = %s', $this->repository->get_query() );
	}

	public function test_get_treats_a_failed_query_as_no_rows(): void {
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( null );

		$this->assertSame( array(), $this->repository->get() );
	}

	public function test_first_limits_to_one_row(): void {
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'SELECT * FROM ' . self::TABLE . ' LIMIT 1', ARRAY_A )
			->andReturn( array() );

		$this->assertNull( $this->repository->first() );
	}

	public function test_count_ignores_ordering_and_paging(): void {
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->with( 'SELECT COUNT(*) FROM ' . self::TABLE . ' AS g WHERE g.status = %s -- ["active"]' )
			->andReturn( '7' );

		$count = $this->repository->alias( 'g' )->where( 'g.status', 'active' )->order_by( 'name' )->limit( 2 )->count();

		$this->assertSame( 7, $count );
	}

	public function test_paginate_counts_then_pages_by_primary_key_when_unordered(): void {
		$this->wpdb->shouldReceive( 'get_var' )->once()->andReturn( '12' );
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'SELECT * FROM ' . self::TABLE . ' WHERE status = %s ORDER BY id ASC LIMIT 5 OFFSET 5 -- ["active"]', ARRAY_A )
			->andReturn( array( array( 'id' => '6', 'name' => 'Sixth' ) ) );

		$page = $this->repository->where( 'status', 'active' )->paginate( 5, 2 );

		$this->assertInstanceOf( Paginator::class, $page );
		$this->assertSame( 12, $page->total() );
		$this->assertSame( 2, $page->current_page() );
		$this->assertSame( 'Sixth', $page->items()[0]->name );
	}

	public function test_paginate_keeps_an_explicit_order_and_clamps_its_arguments(): void {
		$this->wpdb->shouldReceive( 'get_var' )->once()->andReturn( '0' );
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'SELECT * FROM ' . self::TABLE . ' ORDER BY name DESC LIMIT 1', ARRAY_A )
			->andReturn( array() );

		$page = $this->repository->order_by( 'name', 'DESC' )->paginate( 0, 0 );

		$this->assertSame( 1, $page->per_page() );
		$this->assertSame( 1, $page->current_page() );
	}

	public function test_find_all_by_in_skips_the_query_for_no_values(): void {
		$this->wpdb->shouldNotReceive( 'get_results' );

		$this->assertSame( array(), $this->repository->find_all_by_in( 'id', array() ) );
		$this->assertSame( array(), $this->repository->find_many( array() ) );
	}

	public function test_finders_query_by_column(): void {
		$this->wpdb->shouldReceive( 'get_results' )
			->with( 'SELECT * FROM ' . self::TABLE . ' WHERE id = %s LIMIT 1 -- ["4"]', ARRAY_A )
			->once()
			->andReturn( array( array( 'id' => '4', 'name' => 'Four' ) ) );
		$this->wpdb->shouldReceive( 'get_results' )
			->with( 'SELECT * FROM ' . self::TABLE . ' WHERE status = %s -- ["active"]', ARRAY_A )
			->once()
			->andReturn( array() );
		$this->wpdb->shouldReceive( 'get_results' )
			->with( 'SELECT * FROM ' . self::TABLE . ' WHERE id IN (%s,%s) -- [1,2]', ARRAY_A )
			->once()
			->andReturn( array() );

		$this->assertSame( 'Four', $this->repository->find( 4 )->name );
		$this->assertSame( array(), $this->repository->find_all_by( 'status', 'active' ) );
		$this->assertSame( array(), $this->repository->find_all_by_in( 'id', array( 1, 2 ) ) );
	}

	public function test_find_by_slug_requires_a_slug_column(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->repository->find_by_slug( 'lamp' );
	}

	/** ================================
	 *   Model cache
	 *  ================================ */

	public function test_a_caching_repository_serves_repeat_lookups_from_memory(): void {
		$repository = new CachedGadgetRepository();

		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array( array( 'id' => '4', 'name' => 'Four' ) ) );

		$first = $repository->find( 4 );

		$this->assertSame( $first, $repository->find( 4 ) );
		$this->assertSame( $first, ( new CachedGadgetRepository() )->find( 4 ), 'The cache is shared by every instance.' );
	}

	public function test_a_locking_read_skips_the_cache_and_leaves_no_lock_behind(): void {
		$repository = new CachedGadgetRepository();

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'SELECT * FROM ' . self::TABLE . ' WHERE id = %s LIMIT 1 -- ["4"]', ARRAY_A )
			->andReturn( array( array( 'id' => '4', 'name' => 'Four' ) ) );
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'SELECT * FROM ' . self::TABLE . ' WHERE id = %s LIMIT 1 FOR UPDATE -- ["4"]', ARRAY_A )
			->andReturn( array( array( 'id' => '4', 'name' => 'Four, changed' ) ) );

		$repository->find( 4 );

		$this->assertSame( 'Four, changed', $repository->lock_for_update()->find( 4 )->name, 'The row is read and locked, not taken from the cache.' );
		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $repository->get_query() );
	}

	public function test_a_missing_row_is_not_cached(): void {
		$repository = new CachedGadgetRepository();

		$this->wpdb->shouldReceive( 'get_results' )->twice()->andReturn( array() );

		$this->assertNull( $repository->find( 4 ) );
		$this->assertNull( $repository->find( 4 ) );
	}

	public function test_a_non_caching_repository_always_queries(): void {
		$this->wpdb->shouldReceive( 'get_results' )->twice()->andReturn( array( array( 'id' => '4', 'name' => 'Four' ) ) );

		$this->assertNotSame( $this->repository->find( 4 ), $this->repository->find( 4 ) );
	}

	public function test_find_many_only_queries_ids_that_are_not_cached(): void {
		$repository = new CachedGadgetRepository();

		$this->wpdb->shouldReceive( 'get_results' )
			->with( 'SELECT * FROM ' . self::TABLE . ' WHERE id = %s LIMIT 1 -- ["1"]', ARRAY_A )
			->once()
			->andReturn( array( array( 'id' => '1', 'name' => 'One' ) ) );
		$this->wpdb->shouldReceive( 'get_results' )
			->with( 'SELECT * FROM ' . self::TABLE . ' WHERE id IN (%s,%s) -- [2,3]', ARRAY_A )
			->once()
			->andReturn( array( array( 'id' => '2', 'name' => 'Two' ), array( 'id' => '3', 'name' => 'Three' ) ) );

		$one   = $repository->find( 1 );
		$found = $repository->find_many( array( 1, 2, 3 ) );

		$this->assertSame( array( 'One', 'Two', 'Three' ), array_map( static fn( $gadget ) => $gadget->name, $found ) );
		$this->assertSame( $one, $found[0] );
		$this->assertSame( $found[1], $repository->find( 2 ), 'find_many() warms the cache.' );
	}

	public function test_clear_cache_only_clears_the_calling_repository(): void {
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( array( array( 'id' => '4', 'name' => 'Four' ) ) );

		$cached = ( new CachedGadgetRepository() )->find( 4 );
		$other  = ( new OtherCachedGadgetRepository() )->find( 4 );

		OtherCachedGadgetRepository::clear_cache();

		$this->assertSame( $cached, ( new CachedGadgetRepository() )->find( 4 ) );
		$this->assertNotSame( $other, ( new OtherCachedGadgetRepository() )->find( 4 ) );
	}

	/** ================================
	 *   Writes
	 *  ================================ */

	public function test_insert_formats_dates_and_returns_the_new_id(): void {
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with( self::TABLE, array( 'name' => 'Lamp', 'created_at' => '2025-01-02 03:04:05' ) )
			->andReturnUsing(
				function () {
					$this->wpdb->insert_id = 42;

					return 1;
				}
			);

		$this->assertSame( 42, $this->repository->insert( array( 'name' => 'Lamp', 'created_at' => new DateTime( '2025-01-02 03:04:05' ) ) ) );
	}

	public function test_dates_are_written_in_utc_whatever_their_time_zone(): void {
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with( self::TABLE, array( 'name' => 'Lamp', 'created_at' => '2025-01-02 03:04:05' ) )
			->andReturn( 1 );
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with( 'UPDATE ' . self::TABLE . ' SET created_at = %s WHERE id = %s -- ["2025-01-02 03:04:05","4"]' )
			->andReturn( 1 );

		$this->repository->insert( array( 'name' => 'Lamp', 'created_at' => new \DateTimeImmutable( '2025-01-02 08:34:05', new \DateTimeZone( '+05:30' ) ) ) );
		$this->repository->where( 'id', 4 )->update_matching( array( 'created_at' => new DateTime( '2025-01-01 22:04:05', new \DateTimeZone( 'America/New_York' ) ) ) );
	}

	public function test_insert_returns_false_when_the_database_refuses(): void {
		$this->wpdb->shouldReceive( 'insert' )->once()->andReturn( false );

		$this->assertFalse( $this->repository->insert( array( 'name' => 'Lamp' ) ) );
	}

	public function test_insert_validates_its_data(): void {
		try {
			$this->repository->insert( array() );
			$this->fail( 'Expected empty data to be rejected.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertSame( 'Data must be a non-empty array.', $e->getMessage() );
		}

		$this->expectException( InvalidArgumentException::class );
		$this->repository->insert( array( 'colour' => 'red' ) );
	}

	public function test_update_turns_equality_wheres_into_the_where_array(): void {
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->with( self::TABLE, array( 'name' => 'Desk lamp' ), array( 'id' => 4, 'status' => 'active' ) )
			->andReturn( 1 );

		$this->assertSame( 1, $this->repository->where( 'id', 4 )->where( 'status', 'active' )->update( array( 'name' => 'Desk lamp' ) ) );
	}

	public function test_update_validates_its_input(): void {
		$this->assert_throws( InvalidArgumentException::class, fn() => $this->repository->where( 'id', 4 )->update( array() ) );
		$this->assert_throws( RuntimeException::class, fn() => ( new GadgetRepository() )->update( array( 'name' => 'x' ) ) );
		$this->assert_throws( InvalidArgumentException::class, fn() => ( new GadgetRepository() )->where( 'id', '>', 4 )->update( array( 'name' => 'x' ) ) );
		$this->assert_throws( InvalidArgumentException::class, fn() => ( new GadgetRepository() )->where( 'id', 4 )->update( array( 'colour' => 'x' ) ) );
	}

	public function test_save_inserts_new_models_and_updates_existing_ones(): void {
		$this->wpdb->shouldReceive( 'insert' )->once()->with( self::TABLE, array( 'name' => 'New' ) )->andReturnUsing(
			function () {
				$this->wpdb->insert_id = 9;

				return 1;
			}
		);
		$this->wpdb->shouldReceive( 'update' )->once()->with( self::TABLE, array( 'name' => 'Old' ), array( 'id' => 3 ) )->andReturn( 0 );

		$this->assertSame( 9, $this->repository->save( new Gadget( array( 'id' => 0, 'name' => 'New' ) ) ) );
		$this->assertSame( 0, $this->repository->save( new Gadget( array( 'id' => 3, 'name' => 'Old' ) ) ) );
	}

	public function test_insert_stamps_both_timestamps_in_utc_on_a_table_that_has_them(): void {
		$written = null;
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturnUsing(
				function ( $table, $data ) use ( &$written ) {
					$written = $data;

					return 1;
				}
			);

		$before = time();
		( new TimestampedGadgetRepository() )->insert( array( 'name' => 'Lamp' ) );
		$after = time();

		$this->assertSame( array( 'name', 'created_at', 'updated_at' ), array_keys( $written ) );
		$this->assert_utc_now( $before, $after, $written['created_at'] );
		$this->assertSame( $written['created_at'], $written['updated_at'] );
	}

	public function test_insert_keeps_a_timestamp_the_data_already_holds(): void {
		$written = null;
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturnUsing(
				function ( $table, $data ) use ( &$written ) {
					$written = $data;

					return 1;
				}
			);

		( new TimestampedGadgetRepository() )->insert( array( 'name' => 'Lamp', 'created_at' => new DateTime( '2025-01-02 03:04:05' ) ) );

		$this->assertSame( '2025-01-02 03:04:05', $written['created_at'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $written['updated_at'] );
	}

	public function test_update_stamps_only_updated_at_in_utc(): void {
		$written = null;
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->andReturnUsing(
				function ( $table, $data, $where ) use ( &$written ) {
					$written = $data;
					$this->assertEquals( array( 'id' => 4 ), $where );

					return 1;
				}
			);

		$before = time();
		( new TimestampedGadgetRepository() )->where( 'id', 4 )->update( array( 'name' => 'Desk lamp' ) );
		$after = time();

		$this->assertSame( array( 'name', 'updated_at' ), array_keys( $written ) );
		$this->assert_utc_now( $before, $after, $written['updated_at'] );
	}

	public function test_update_matching_and_increment_stamp_updated_at(): void {
		$queries = array();
		$this->wpdb->shouldReceive( 'query' )
			->twice()
			->andReturnUsing(
				function ( $query ) use ( &$queries ) {
					$queries[] = $query;

					return 1;
				}
			);

		$repository = new TimestampedGadgetRepository();
		$repository->where( 'id', 4 )->update_matching( array( 'name' => 'Desk lamp' ) );
		$repository->where( 'id', 4 )->increment( 'price' );

		$this->assertMatchesRegularExpression( '/^UPDATE ' . self::TABLE . ' SET name = %s, updated_at = %s WHERE id = %s -- \["Desk lamp","[\d: -]{19}","4"\]$/', $queries[0] );
		$this->assertMatchesRegularExpression( '/^UPDATE ' . self::TABLE . ' SET price = price \+ %d, updated_at = %s WHERE id = %s -- \[1,"[\d: -]{19}","4"\]$/', $queries[1] );
	}

	public function test_a_saved_model_takes_the_timestamps_the_write_stamped(): void {
		$written = array();
		$this->wpdb->shouldReceive( 'insert' )->once()->andReturnUsing(
			function ( $table, $data ) use ( &$written ) {
				$written[]             = $data;
				$this->wpdb->insert_id = 9;

				return 1;
			}
		);
		$this->wpdb->shouldReceive( 'update' )->once()->andReturnUsing(
			function ( $table, $data ) use ( &$written ) {
				$written[] = $data;

				return 1;
			}
		);

		$repository = new TimestampedGadgetRepository();
		$model      = new StampedGadget( array( 'id' => 0, 'name' => 'New' ) );

		$this->assertSame( 9, $repository->save( $model ) );
		$this->assertSame( $written[0]['created_at'], $model->created_at->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( $written[0]['updated_at'], $model->updated_at->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( 'UTC', $model->updated_at->getTimezone()->getName() );

		$model->id         = 9;
		$model->updated_at = new DateTime( '2020-01-02 03:04:05' );
		$this->assertSame( 1, $repository->save( $model ) );
		$this->assertArrayNotHasKey( 'created_at', $written[1] );
		$this->assertSame( $written[1]['updated_at'], $model->updated_at->format( 'Y-m-d H:i:s' ) );
	}

	public function test_a_saved_model_takes_its_timestamps_in_the_type_its_properties_declare(): void {
		$this->wpdb->shouldReceive( 'insert' )->twice()->andReturn( 1 );

		$repository = new TimestampedGadgetRepository();
		$strings    = new StringStampedGadget( array( 'id' => 0, 'name' => 'Strings' ) );
		$immutable  = new ImmutableStampedGadget( array( 'id' => 0, 'name' => 'Immutable' ) );

		$repository->save( $strings );
		$repository->save( $immutable );

		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $strings->created_at );
		$this->assertSame( $strings->created_at, $strings->updated_at );
		$this->assertInstanceOf( DateTime::class, $immutable->created_at, 'A property typed as the interface gets a DateTime.' );
		$this->assertInstanceOf( \DateTimeImmutable::class, $immutable->updated_at );
		$this->assertSame( 'UTC', $immutable->updated_at->getTimezone()->getName() );
	}

	public function test_a_model_that_fails_to_save_keeps_its_timestamps(): void {
		$this->wpdb->shouldReceive( 'update' )->once()->andReturn( false );

		$model             = new StampedGadget( array( 'id' => 4, 'name' => 'Old' ) );
		$model->updated_at = new DateTime( '2020-01-02 03:04:05' );

		$this->assertFalse( ( new TimestampedGadgetRepository() )->save( $model ) );
		$this->assertSame( '2020-01-02 03:04:05', $model->updated_at->format( 'Y-m-d H:i:s' ) );
	}

	public function test_touch_stamps_only_updated_at_of_one_row(): void {
		$written = null;
		$this->wpdb->shouldReceive( 'update' )->once()->andReturnUsing(
			function ( $table, $data, $where ) use ( &$written ) {
				$written = $data;
				$this->assertEquals( array( 'id' => 4 ), $where );

				return 1;
			}
		);

		$before = time();
		$this->assertSame( 1, ( new TimestampedGadgetRepository() )->touch( 4 ) );
		$this->assertSame( array( 'updated_at' ), array_keys( $written ) );
		$this->assert_utc_now( $before, time(), $written['updated_at'] );
	}

	public function test_touch_refuses_a_table_without_timestamps(): void {
		$this->wpdb->shouldNotReceive( 'update' );

		$this->assert_throws( \LogicException::class, fn() => $this->repository->touch( 4 ) );
		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query() );
	}

	public function test_a_write_that_sets_a_timestamp_itself_is_not_stamped_again(): void {
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->with( self::TABLE, array( 'updated_at' => '2025-01-02 03:04:05' ), array( 'id' => 4 ) )
			->andReturn( 1 );

		$this->assertSame( 1, ( new TimestampedGadgetRepository() )->where( 'id', 4 )->update( array( 'updated_at' => '2025-01-02 03:04:05' ) ) );
	}

	public function test_delete_by_id_or_by_equality_wheres(): void {
		$this->wpdb->shouldReceive( 'delete' )->once()->with( self::TABLE, array( 'id' => 4 ) )->andReturn( 1 );
		$this->wpdb->shouldReceive( 'delete' )->once()->with( self::TABLE, array( 'status' => 'trash' ) )->andReturn( 3 );

		$this->assertSame( 1, $this->repository->delete( 4 ) );
		$this->assertSame( 3, $this->repository->where( 'status', 'trash' )->delete() );
	}

	public function test_delete_validates_its_wheres(): void {
		$this->assert_throws( RuntimeException::class, fn() => $this->repository->delete() );
		$this->assert_throws( InvalidArgumentException::class, fn() => ( new GadgetRepository() )->where( 'id', 'IN', array( 1 ) )->delete() );
	}

	/**
	 * WHERE clauses that $wpdb->update() and $wpdb->delete() cannot express, which would widen or change the rows
	 * they match if written as an equality array.
	 *
	 * @return array<string, array{callable}>
	 */
	public function inexpressible_where_provider(): array {
		return array(
			'an OR'                   => array( static fn( $query ) => $query->where( 'id', 4 )->where( 'status', '=', 'active', 'OR' ) ),
			'an OR in lowercase'      => array( static fn( $query ) => $query->where( 'id', 4 )->where( 'status', '=', 'active', 'or' ) ),
			'a column compared twice' => array( static fn( $query ) => $query->where( 'id', 4 )->where( 'id', 5 ) ),
			'a null comparison'       => array( static fn( $query ) => $query->where( 'id', 4 )->where( 'name', 'IS', null ) ),
			'a qualified column'      => array( static fn( $query ) => $query->where( self::TABLE . '.id', 4 ) ),
			'an aliased column'       => array( static fn( $query ) => $query->where( 'id AS x` = 1 OR `id', 4 ) ),
		);
	}

	/**
	 * @dataProvider unsettable_column_provider
	 */
	public function test_insert_and_update_only_write_bare_column_names( string $column ): void {
		$this->wpdb->shouldNotReceive( 'insert' );
		$this->wpdb->shouldNotReceive( 'update' );

		$attempts = array(
			fn() => $this->repository->insert( array( $column => 'x' ) ),
			fn() => $this->repository->where( 'id', 4 )->update( array( $column => 'x' ) ),
		);

		foreach ( $attempts as $attempt ) {
			$this->assert_throws( InvalidArgumentException::class, $attempt );
			$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query(), 'The query being built is discarded.' );
		}
	}

	/**
	 * @dataProvider inexpressible_where_provider
	 */
	public function test_update_and_delete_refuse_wheres_they_cannot_express( callable $wheres ): void {
		$this->wpdb->shouldNotReceive( 'update' );
		$this->wpdb->shouldNotReceive( 'delete' );

		$attempts = array(
			fn() => $wheres( $this->repository )->update( array( 'name' => 'x' ) ),
			fn() => $wheres( $this->repository )->delete(),
		);

		foreach ( $attempts as $attempt ) {
			$this->assert_throws( InvalidArgumentException::class, $attempt );
			$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query(), 'The query being built is discarded.' );
		}
	}

	public function test_delete_by_id_refuses_a_where_on_the_same_column(): void {
		$this->wpdb->shouldNotReceive( 'delete' );

		$this->assert_throws( InvalidArgumentException::class, fn() => $this->repository->where( 'id', 5 )->delete( 4 ) );
	}

	public function test_an_or_on_the_first_clause_joins_nothing_and_is_accepted(): void {
		$this->wpdb->shouldReceive( 'update' )->once()->with( self::TABLE, array( 'name' => 'x' ), array( 'id' => 4, 'status' => 'active' ) )->andReturn( 1 );
		$this->wpdb->shouldReceive( 'delete' )->once()->with( self::TABLE, array( 'id' => 4 ) )->andReturn( 1 );

		$this->assertSame( 1, $this->repository->where( 'id', '=', 4, 'OR' )->where( 'status', 'active' )->update( array( 'name' => 'x' ) ) );
		$this->assertSame( 1, $this->repository->where( 'id', '=', 4, 'OR' )->delete() );
	}

	public function test_delete_raw_supports_any_operator(): void {
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with( 'DELETE FROM ' . self::TABLE . ' WHERE id IN (%s,%s) -- [1,2]' )
			->andReturn( 2 );

		$this->assertSame( 2, $this->repository->where( 'id', 'IN', array( 1, 2 ) )->delete_raw() );
		$this->assert_throws( RuntimeException::class, fn() => $this->repository->delete_raw() );
	}

	public function test_execute_raw_prepares_only_when_given_bindings(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->with( 'OPTIMIZE TABLE x' )->andReturn( true );
		$this->wpdb->shouldReceive( 'query' )->once()->with( 'DELETE FROM x WHERE id = %d -- [3]' )->andReturn( 1 );

		$this->assertTrue( $this->repository->execute_raw( 'OPTIMIZE TABLE x' ) );
		$this->assertSame( 1, $this->repository->execute_raw( 'DELETE FROM x WHERE id = %d', array( 'id' => 3 ) ) );
	}

	public function test_insert_raw_builds_a_multi_row_insert(): void {
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with( 'INSERT INTO ' . self::TABLE . ' (name, price) VALUES (%s, %d), (%s, %d) -- ["a",1,"b",2]' )
			->andReturn( 2 );

		$this->assertSame( 2, $this->repository->insert_raw_public( 'name, price', array( '(%s, %d)', '(%s, %d)' ), array( 'a', 1, 'b', 2 ) ) );
	}

	public function test_insert_raw_reports_failure_and_validates_input(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( false );

		$this->assertFalse( $this->repository->insert_raw_public( 'name', array( '(%s)' ), array( 'a' ) ) );
		$this->assert_throws( InvalidArgumentException::class, fn() => $this->repository->insert_raw_public( ' ', array( '(%s)' ), array( 'a' ) ) );
		$this->assert_throws( InvalidArgumentException::class, fn() => $this->repository->insert_raw_public( 'name', array(), array( 'a' ) ) );
		$this->assert_throws( InvalidArgumentException::class, fn() => $this->repository->insert_raw_public( 'name', array( '(%s)' ), array() ) );
	}

	public function test_update_raw_builds_an_update_from_a_raw_set_clause(): void {
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with( 'UPDATE ' . self::TABLE . ' SET price = price + %d WHERE status IS NOT NULL -- [1]' )
			->andReturn( 4 );

		$this->assertSame( 4, $this->repository->where( 'status', 'IS NOT', null )->update_raw_public( 'price = price + %d', array( 1 ) ) );
	}

	public function test_update_raw_binds_set_values_before_where_values(): void {
		// The SET placeholder comes first in the SQL, so its value must be bound first.
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with( 'UPDATE ' . self::TABLE . ' SET price = price + %d WHERE status = %s  AND name = %s -- [1,"active","Lamp"]' )
			->andReturn( 4 );

		$this->assertSame(
			4,
			$this->repository->where( 'status', 'active' )->where( 'name', 'Lamp' )->update_raw_public( 'price = price + %d', array( 1 ) )
		);
	}

	public function test_update_raw_validates_input(): void {
		$this->assert_throws( InvalidArgumentException::class, fn() => $this->repository->where( 'id', 1 )->update_raw_public( '', array() ) );
		$this->assert_throws( RuntimeException::class, fn() => ( new GadgetRepository() )->update_raw_public( 'price = 1', array() ) );
	}

	public function test_increment_adds_to_a_column_and_sets_the_others_under_any_where(): void {
		// The SET values are bound first, in order, then the WHERE values; NULL is written without a binding.
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with(
				'UPDATE ' . self::TABLE . ' SET price = price + %d, status = %s, name = NULL, created_at = %s WHERE id = %s  AND price < %d  AND ( status = %s  OR status = %s ) -- [1,"sold","2026-10-02 06:14:03","4",9,"active","draft"]'
			)
			->andReturn( 1 );

		$changed = $this->repository
			->where( 'id', 4 )
			->where( 'price', '<', 9 )
			->where_group( fn( $query ) => $query->where( 'status', 'active' )->where( 'status', '=', 'draft', 'OR' ) )
			->increment(
				'PRICE',
				1,
				array(
					'status'     => 'sold',
					'Name'       => null,
					'created_at' => new DateTime( '2026-10-02 06:14:03' ),
				)
			);

		$this->assertSame( 1, $changed );
		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query(), 'The builder is reset after the update.' );
	}

	public function test_increment_can_subtract_without_other_columns(): void {
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with( 'UPDATE ' . self::TABLE . ' SET price = price + %d WHERE status = %s -- [-2,"active"]' )
			->andReturn( 3 );

		$this->assertSame( 3, $this->repository->where( 'status', 'active' )->increment( 'price', -2 ) );
	}

	public function test_increment_reports_a_failed_query(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( false );

		$this->assertFalse( $this->repository->where( 'id', 4 )->increment( 'price' ) );
	}

	public function test_increment_validates_its_columns_and_wheres_and_discards_the_query(): void {
		$attempts = array(
			fn() => $this->repository->where( 'id', 4 )->increment( 'colour' ),
			fn() => $this->repository->where( 'id', 4 )->increment( 'price', 1, array( 'colour' => 'red' ) ),
			fn() => $this->repository->increment( 'price' ),
		);

		foreach ( $attempts as $attempt ) {
			$this->assert_throws( \Throwable::class, $attempt );
			$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query() );
		}

		$this->assert_throws( RuntimeException::class, fn() => $this->repository->increment( 'price' ) );
	}

	/**
	 * Columns a SELECT accepts but a SET clause cannot write.
	 *
	 * @return array<string, array{string}>
	 */
	public function unsettable_column_provider(): array {
		return array(
			'qualified with the table' => array( self::TABLE . '.price' ),
			'qualified with an alias'  => array( 'g.price' ),
			'with an alias'            => array( 'price AS cost' ),
			'star'                     => array( '*' ),
			'breaking out of quotes'   => array( 'price AS p`, `status' ),
			'empty'                    => array( '' ),
		);
	}

	/**
	 * @dataProvider unsettable_column_provider
	 */
	public function test_increment_only_sets_bare_column_names( string $column ): void {
		$this->wpdb->shouldNotReceive( 'query' );

		$attempts = array(
			fn() => $this->repository->where( 'id', 4 )->increment( $column ),
			fn() => $this->repository->where( 'id', 4 )->increment( 'price', 1, array( $column => 'x' ) ),
		);

		foreach ( $attempts as $attempt ) {
			$this->assert_throws( InvalidArgumentException::class, $attempt );
			$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query(), 'The query being built is discarded.' );
		}
	}

	public function test_increment_rejects_extra_columns_given_as_a_list(): void {
		$this->wpdb->shouldNotReceive( 'query' );

		$this->assert_throws( InvalidArgumentException::class, fn() => $this->repository->where( 'id', 4 )->increment( 'price', 1, array( 'sold' ) ) );
	}

	public function test_update_matching_sets_columns_under_any_where(): void {
		// The SET values are bound first, in order, then the WHERE values; NULL is written without a binding.
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with(
				'UPDATE ' . self::TABLE . ' SET status = %s, name = NULL, price = %d, created_at = %s WHERE price < %d  AND ( status = %s  OR created_at <= %s ) -- ["sold",3,"2026-10-02 06:14:03",9,"active","2026-10-01 00:00:00"]'
			)
			->andReturn( 2 );

		$changed = $this->repository
			->where( 'price', '<', 9 )
			->where_group( fn( $query ) => $query->where( 'status', 'active' )->where( 'created_at', '<=', '2026-10-01 00:00:00', 'OR' ) )
			->update_matching(
				array(
					'status'     => 'sold',
					'Name'       => null,
					'price'      => 3,
					'created_at' => new DateTime( '2026-10-02 06:14:03' ),
				)
			);

		$this->assertSame( 2, $changed );
		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query(), 'The builder is reset after the update.' );
	}

	public function test_update_matching_reports_a_failed_query(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( false );

		$this->assertFalse( $this->repository->where( 'price', '>', 4 )->update_matching( array( 'status' => 'sold' ) ) );
	}

	public function test_update_matching_validates_its_columns_and_wheres_and_discards_the_query(): void {
		$this->wpdb->shouldNotReceive( 'query' );

		$attempts = array(
			InvalidArgumentException::class => fn() => $this->repository->where( 'id', 4 )->update_matching( array() ),
			RuntimeException::class         => fn() => $this->repository->update_matching( array( 'status' => 'sold' ) ),
		);

		foreach ( $attempts as $exception => $attempt ) {
			$this->assert_throws( $exception, $attempt );
			$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query() );
		}

		$this->assert_throws( \Throwable::class, fn() => $this->repository->where( 'id', 4 )->update_matching( array( 'colour' => 'red' ) ) );
		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query() );
	}

	/**
	 * @dataProvider unsettable_column_provider
	 */
	public function test_update_matching_only_sets_bare_column_names( string $column ): void {
		$this->wpdb->shouldNotReceive( 'query' );

		$this->assert_throws( InvalidArgumentException::class, fn() => $this->repository->where( 'id', 4 )->update_matching( array( $column => 'x' ) ) );
		$this->assertSame( 'SELECT * FROM ' . self::TABLE, $this->repository->get_query(), 'The query being built is discarded.' );
	}

	public function test_update_matching_rejects_columns_given_as_a_list(): void {
		$this->wpdb->shouldNotReceive( 'query' );

		$this->assert_throws( InvalidArgumentException::class, fn() => $this->repository->where( 'id', 4 )->update_matching( array( 'sold' ) ) );
	}

	public function test_last_db_error_is_read_from_wpdb(): void {
		$this->wpdb->last_error = 'Duplicate entry';

		$this->assertSame( 'Duplicate entry', $this->repository->get_last_db_error() );
	}

	/** ================================
	 *   Transactions
	 *  ================================ */

	public function test_transaction_commits_and_returns_the_callback_result(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'START TRANSACTION' );
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'COMMIT' );

		$received = null;
		$result   = $this->repository->transaction(
			static function ( $repository ) use ( &$received ) {
				$received = $repository;

				return 0;
			}
		);

		$this->assertSame( 0, $result, 'Only false means failure.' );
		$this->assertSame( $this->repository, $received );
	}

	public function test_transaction_rolls_back_when_the_callback_returns_false(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'START TRANSACTION' );
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'ROLLBACK' );

		$this->assertFalse( $this->repository->transaction( static fn() => false ) );
	}

	public function test_a_transaction_the_database_cannot_start_runs_nothing(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->with( 'START TRANSACTION' )->andReturn( false );
		$this->wpdb->shouldNotReceive( 'query' )->with( 'COMMIT' );
		$this->wpdb->shouldNotReceive( 'query' )->with( 'ROLLBACK' );

		$ran = false;

		$this->assertFalse(
			$this->repository->transaction(
				static function () use ( &$ran ) {
					$ran = true;

					return 1;
				}
			)
		);
		$this->assertFalse( $ran, 'Its writes would not be atomic.' );
	}

	public function test_a_transaction_the_database_cannot_commit_is_rolled_back_and_fails(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'START TRANSACTION' );
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'COMMIT' )->andReturnUsing(
			function () {
				$this->wpdb->last_error = 'Lost connection';

				return false;
			}
		);
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'ROLLBACK' )->andReturnUsing(
			function () {
				$this->wpdb->last_error = '';
			}
		);

		$this->assertFalse( $this->repository->transaction( static fn() => 1 ) );
		$this->assertSame( 'Lost connection', $this->repository->get_last_db_error(), 'The commit error is kept.' );
	}

	public function test_a_transaction_whose_commit_ran_again_on_a_new_connection_is_rolled_back_and_fails(): void {
		$this->wpdb->dbh = new \stdClass();
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'START TRANSACTION' );
		// The server went away, so $wpdb reconnected and ran the COMMIT on a connection without the transaction.
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'COMMIT' )->andReturnUsing(
			function () {
				$this->wpdb->dbh = new \stdClass();

				return 0;
			}
		);
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'ROLLBACK' );

		$this->assertFalse( $this->repository->transaction( static fn() => 1 ) );
		$this->assertSame( 'The database connection was lost and reopened during the transaction, so its writes may be partly kept.', $this->repository->get_last_db_error() );
	}

	public function test_a_transaction_whose_connection_was_reopened_inside_it_fails_after_its_commit(): void {
		$this->wpdb->dbh = new \stdClass();
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'START TRANSACTION' );
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'COMMIT' );
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'ROLLBACK' );

		$result = $this->repository->transaction(
			function () {
				// A write inside it met a server that went away, and ran again on a new connection.
				$this->wpdb->dbh = new \stdClass();

				return 1;
			}
		);

		$this->assertFalse( $result );
		$this->assertSame( 'The database connection was lost and reopened during the transaction, so its writes may be partly kept.', $this->repository->get_last_db_error() );
	}

	public function test_a_transaction_whose_read_went_to_another_connection_still_commits(): void {
		$primary         = new \stdClass();
		$this->wpdb->dbh = $primary;
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'START TRANSACTION' );
		// Back on the connection the START went to, as a drop-in sends every write and the COMMIT.
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'COMMIT' )->andReturnUsing(
			function () use ( $primary ) {
				$this->wpdb->dbh = $primary;

				return 0;
			}
		);
		$this->wpdb->shouldNotReceive( 'query' )->with( 'ROLLBACK' );

		$result = $this->repository->transaction(
			function () {
				// A database drop-in sent a read to a replica.
				$this->wpdb->dbh = new \stdClass();

				return 1;
			}
		);

		$this->assertSame( 1, $result );
	}

	public function test_a_transaction_on_the_same_connection_throughout_commits(): void {
		$this->wpdb->dbh = new \stdClass();
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'START TRANSACTION' );
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'COMMIT' );

		$this->assertSame( 1, $this->repository->transaction( static fn() => 1 ) );
	}

	public function test_transaction_rolls_back_and_rethrows(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'START TRANSACTION' );
		$this->wpdb->shouldReceive( 'query' )->once()->ordered()->with( 'ROLLBACK' );

		$this->expectException( \LogicException::class );

		$this->repository->transaction(
			static function () {
				throw new \LogicException( 'Boom' );
			}
		);
	}

	/**
	 * @dataProvider failing_transaction_provider
	 */
	public function test_a_failed_transaction_keeps_the_error_that_caused_it( callable $callback ): void {
		$this->wpdb->shouldReceive( 'query' )->with( 'START TRANSACTION' );
		// Like every $wpdb query, the ROLLBACK resets last_error.
		$this->wpdb->shouldReceive( 'query' )->with( 'ROLLBACK' )->andReturnUsing(
			function () {
				$this->wpdb->last_error = '';
			}
		);

		try {
			$this->repository->transaction(
				function () use ( $callback ) {
					$this->wpdb->last_error = "Duplicate entry 'x' for key 'unique'";

					return $callback();
				}
			);
		} catch ( \LogicException $e ) {
			unset( $e );
		}

		$this->assertSame( "Duplicate entry 'x' for key 'unique'", $this->repository->get_last_db_error() );
	}

	public function failing_transaction_provider(): array {
		return array(
			'returns false' => array( static fn() => false ),
			'throws'        => array(
				static function () {
					throw new \LogicException( 'Boom' );
				},
			),
		);
	}

	/**
	 * Expect the joined wp_parts table to be described exactly once, with columns that differ from this table's.
	 *
	 * @return void
	 */
	private function expect_parts_described_once(): void {
		$this->wpdb->shouldReceive( 'get_col' )
			->once()
			->with( 'DESCRIBE wp_parts' )
			->andReturn( array( 'id', 'gadget_id', 'sku' ) );
	}

	/**
	 * Assert a DATETIME value is the UTC time of a moment between two Unix times.
	 *
	 * @param int    $before
	 * @param int    $after
	 * @param string $value
	 *
	 * @return void
	 */
	private function assert_utc_now( int $before, int $after, string $value ): void {
		$time = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new \DateTimeZone( 'UTC' ) );

		$this->assertNotFalse( $time, "$value is not a DATETIME." );
		$this->assertGreaterThanOrEqual( $before, $time->getTimestamp() );
		$this->assertLessThanOrEqual( $after, $time->getTimestamp() );
	}

	/**
	 * Assert that a callback throws the given exception class.
	 *
	 * @param string   $exception_class
	 * @param callable $callback
	 *
	 * @return void
	 */
	private function assert_throws( string $exception_class, callable $callback ): void {
		try {
			$callback();
		} catch ( \Throwable $e ) {
			$this->assertInstanceOf( $exception_class, $e );

			return;
		}

		$this->fail( "Expected {$exception_class} to be thrown." );
	}
}

/**
 * JSON-encode prepare() bindings for readable assertions.
 *
 * @param array $args
 *
 * @return string
 */
function wp_json_encode_stub( array $args ): string {
	return (string) json_encode( array_values( $args ), JSON_PRESERVE_ZERO_FRACTION ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
}

class Gadget extends BaseModel {
	public int $id;
	public string $name;

	public function __construct( array $data ) {
		$this->id   = (int) ( $data['id'] ?? 0 );
		$this->name = $data['name'];
	}
}

class GadgetRepository extends BaseRepository {
	public function __construct() {
		parent::__construct( 'gadgets', Gadget::class );
	}

	public function where_raw_public( string $condition, string $logical_operator = 'AND' ): self {
		return $this->where_raw( $condition, $logical_operator );
	}

	public function order_by_raw_public( string $expression ): self {
		return $this->order_by_raw( $expression );
	}

	public function insert_raw_public( string $columns, array $rows, array $bindings ): int|false {
		return $this->insert_raw( $columns, $rows, $bindings );
	}

	public function update_raw_public( string $set_clause, array $bindings ): int|false {
		return $this->update_raw( $set_clause, $bindings );
	}
}

class StampedGadget extends Gadget {
	public DateTime $created_at;
	public DateTime $updated_at;
}

class StringStampedGadget extends Gadget {
	public string $created_at;
	public string $updated_at;
}

class ImmutableStampedGadget extends Gadget {
	public \DateTimeInterface $created_at;
	public \DateTimeImmutable $updated_at;
}

class TimestampedGadgetRepository extends GadgetRepository {
	protected bool $timestamps = true;
}

class CachedGadgetRepository extends GadgetRepository {
	protected bool $enable_cache = true;
}

class OtherCachedGadgetRepository extends GadgetRepository {
	protected bool $enable_cache = true;
}
