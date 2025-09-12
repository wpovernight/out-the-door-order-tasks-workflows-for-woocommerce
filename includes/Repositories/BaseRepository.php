<?php

namespace WPO\AOM\Repositories;

use InvalidArgumentException;
use WPO\AOM\Models\BaseModel;

defined( 'ABSPATH' ) || exit;

abstract class BaseRepository {

	protected \wpdb $wpdb;
	private string $plugin_table_prefix = 'wpo_aom_';
	private string $table_name;

	/** @var class-string<BaseModel> */
	private string $model_class;

	/**
	 * Each where item is: array{0:string column, 1:string operator, 2:mixed value, 3:string logic AND|OR}
	 *
	 * @var array<int, array{0:string,1:string,2:mixed,3:string}>
	 */
	private array $wheres = array();

	/** @var array<int, mixed> */
	private array $bindings = array();

	private array $columns   = array( '*' );
	private string $order_by = '';
	private int $limit       = 0;
	private int $offset      = 0;

	private static ?array $column_names = null;

	/**
	 * Constructor.
	 *
	 * @param string $table_name
	 * @param class-string<BaseModel> $model_class
	 */
	protected function __construct( string $table_name, string $model_class ) {
		global $wpdb;

		$this->wpdb        = $wpdb;
		$this->table_name  = $table_name;
		$this->model_class = $model_class;
	}

	/** ================================
	 *   CRUD Operations
	 *  ================================ */

	/**
	 * Get records.
	 *
	 * @return array<int, BaseModel>
	 */
	public function get( bool $reset = true ): array {
		$columns = implode( ', ', $this->columns );
		$query   = "SELECT {$columns} FROM {$this->get_table_full_name()}";
		$query   = $this->append_query_clauses( $query );

		if ( ! empty( $this->bindings ) ) {
			$query = $this->wpdb->prepare( $query, ...array_values( $this->bindings ) );
		}

		$result = $this->wpdb->get_results( $query, ARRAY_A ) ?? array();

		if ( $reset ) {
			$this->reset_query();
		}

		return array_map( array( $this, 'map_to_model' ), $result );
	}

	/**
	 * Find a record by ID.
	 *
	 * @param int $id Record ID.
	 *
	 * @return BaseModel|null
	 */
	public function find( int $id ): ?BaseModel {
		$results = $this->where( 'id', absint( $id ) )->limit( 1 )->get();

		return ! empty( $results ) ? reset( $results ) : null;
	}

	/**
	 * Get first record.
	 *
	 * @return BaseModel|null
	 */
	public function first(): ?BaseModel {
		$results = $this->limit( 1 )->get();

		return ! empty( $results ) ? reset( $results ) : null;
	}

	/**
	 * Insert a record.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int|false Insert id or false on failure.
	 */
	public function insert( array $data ) {
		if ( empty( $data ) ) {
			throw new InvalidArgumentException( 'Data must be a non-empty array.' );
		}

		$this->validate_columns( array_keys( $data ) );

		$result = $this->wpdb->insert( $this->get_table_full_name(), $data );

		$this->reset_query();

		return $result ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Update records.
	 *
	 * @param array<string, mixed> $data Columns to set.
	 *
	 * @return bool
	 */
	public function update( array $data ): bool {
		// Validate the data array.
		if ( empty( $data ) ) {
			throw new InvalidArgumentException( 'Data must be a non-empty array.' );
		}

		// Ensure that the WHERE clause is set.
		if ( empty( $this->wheres ) ) {
			throw new \RuntimeException( 'No WHERE clause specified for update.' );
		}

		$this->validate_columns( array_keys( $data ) );

		// Prepare WHERE array
		// This method compiles the WHERE clauses using the `where` method of the query builder.
		// It assumes that the WHERE clauses only contain equality checks (i.e., `=` operator).
		$where = array();
		foreach ( $this->wheres as [$column, $operator, $value, $logical_operator] ) {
			$where[ $column ] = $value;
		}

		$this->reset_query();

		return (bool) $this->wpdb->update( $this->get_table_full_name(), $data, $where );
	}


	/**
	 * Delete records from the table based on the WHERE clause.
	 *
	 * @return int|false Number of rows deleted or false on failure.
	 */
	public function delete(): int {
		if ( empty( $this->wheres ) ) {
			throw new \RuntimeException( 'No WHERE clause specified for delete.' );
		}

		// Prepare WHERE array
		// This method compiles the WHERE clauses using the `where` method of the query builder.
		// It assumes that the WHERE clauses only contain equality checks (i.e., `=` operator).
		$where = array();
		foreach ( $this->wheres as [$column, $operator, $value, $logical_operator] ) {
			$where[ $column ] = $value;
		}

		$this->reset_query();

		return (int) $this->wpdb->delete( $this->get_table_full_name(), $where );
	}

	/** ================================
	 *   Helpers
	 *  ================================ */

	/**
	 * Map a single row to a model instance.
	 *
	 * @param array<string, mixed> $row
	 *
	 * @return BaseModel
	 */
	protected function map_to_model( array $row ): BaseModel {
		return new $this->model_class( $row );
	}

	/**
	 * Get the full table name with prefix.
	 *
	 * @return string
	 */
	protected function get_table_full_name(): string {
		return $this->wpdb->prefix . $this->plugin_table_prefix . $this->table_name;
	}

	/**
	 * Get column names from the table.
	 * This caches the column names to avoid repeated queries.
	 *
	 * @return array
	 */
	protected function get_column_names(): array {
		if ( isset( self::$column_names[ $this->table_name ] ) ) {
			return self::$column_names[ $this->table_name ];
		}

		// WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct query is safe here as table name is validated internally and cannot be parameterized.
		$columns = (array) $this->wpdb->get_col( "DESCRIBE {$this->get_table_full_name()}" );
		$columns = array_map( 'strtolower', $columns );

		self::$column_names[ $this->table_name ] = $columns;

		return $columns;
	}

	/** ================================
	 *   Core Query Builder Methods
	 *  ================================ */

	/**
	 * Select columns.
	 *
	 * @param array<int,string> $columns Columns.
	 *
	 * @return $this
	 */
	public function select( array $columns ): self {
		$this->validate_columns( $columns );
		$this->columns = $columns;

		return $this;
	}

	/**
	 * Add a WHERE clause to the query.
	 *
	 * @param string $column
	 * @param string $operator Operator (=, !=, <, >, <=, >=, LIKE, NOT LIKE, IN, NOT IN, IS, IS NOT).
	 * @param mixed|null $value Value (null only allowed with IS / IS NOT).
	 * @param string $logical_operator AND|OR (default AND).
	 *
	 * @return self
	 */
	public function where( string $column, string $operator, $value = null, string $logical_operator = 'AND' ): self {
		// If only two arguments are provided, assume the operator is '='.
		if ( 2 === func_num_args() ) {
			$value    = $operator;
			$operator = '=';
		}

		$this->validate_columns( (array) $column );
		$this->validate_operator( $operator );
		$this->validate_logical_operator( $logical_operator );

		if ( $value === null ) {
			if ( ! in_array( $operator, array( 'IS', 'IS NOT' ) ) ) {
				throw new InvalidArgumentException( "Cannot use NULL with operator {$operator}" );
			}
			$value = 'NULL';
		}

		// Add the WHERE clause to the query.
		$this->wheres[] = array(
			strtolower( $column ),
			strtoupper( $operator ),
			$value,
			strtoupper( $logical_operator )
		);

		// Add the value to the bindings array.
		$this->add_binding( $value );

		return $this;
	}

	/**
	 * Add an ORDER BY clause to the query.
	 *
	 * @param string $column
	 * @param string $direction (ASC|DESC)
	 *
	 * @return self
	 */
	public function order_by( string $column, string $direction = 'ASC' ): self {
		$direction = strtoupper( $direction );

		$this->validate_columns( (array) $column );
		$this->validate_direction( $direction );

		$this->order_by = " ORDER BY {$column} {$direction}";

		return $this;
	}

	/**
	 * Set a limit on the number of records returned.
	 *
	 * @param int $limit
	 *
	 * @return $this
	 */
	public function limit( int $limit ): self {
		$this->limit = absint( $limit );

		return $this;
	}

	/**
	 * Set an offset for the records returned.
	 *
	 * @param int $offset
	 *
	 * @return $this
	 */
	public function offset( int $offset ): self {
		$this->offset = absint( $offset );

		return $this;
	}

	/** ================================
	 *   Internal
	 *  ================================ */

	/**
	 * Append the WHERE, ORDER BY, LIMIT, and OFFSET clauses to the query.
	 *
	 * @param string $query
	 *
	 * @return string
	 */
	private function append_query_clauses( string $query ): string {
		$query .= $this->compile_where();
		$query .= $this->order_by;
		$query .= $this->compile_limit_offset();

		return $query;
	}

	/**
	 * Compile the WHERE clauses into a SQL string.
	 *
	 * @return string
	 */
	private function compile_where(): string {
		if ( empty( $this->wheres ) ) {
			return '';
		}

		$parts = array();

		foreach ( $this->wheres as $index => $where ) {
			[ $column, $operator, $value, $logical_operator ] = $where;

			$prefix = ( 0 === $index ) ? '' : " {$logical_operator} ";

			if ( is_array( $value ) && in_array( $operator, array( 'IN', 'NOT IN' ), true ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $value ), '%s' ) );
				$parts[]      = "{$prefix}{$column} {$operator} ({$placeholders})";
			} else {
				// Choose a placeholder based on scalar type.
				$placeholder = $this->placeholder_for( $value );
				$parts[]     = "{$prefix}{$column} {$operator} {$placeholder}";
			}
		}

		return ' WHERE ' . implode( ' ', $parts );
	}

	/**
	 * Compile the LIMIT and OFFSET clauses into a SQL string.
	 *
	 * @return string
	 */
	private function compile_limit_offset(): string {
		$limit_offset = '';

		if ( $this->limit > 0 ) {
			$limit_offset .= ' LIMIT ' . $this->limit;
		}

		if ( $this->offset > 0 ) {
			$limit_offset .= ' OFFSET ' . $this->offset;
		}

		return $limit_offset;
	}

	/**
	 * Choose a placeholder for a value.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	private function placeholder_for( $value ): string {
		if ( is_int( $value ) ) {
			return '%d';
		}
		if ( is_float( $value ) ) {
			return '%f';
		}

		return '%s';
	}

	/**
	 * Add a binding (merging arrays for IN()).
	 *
	 * @param mixed $value
	 *
	 * @return void
	 */
	private function add_binding( $value ): void {
		if ( is_array( $value ) ) {
			$this->bindings = array_merge( $this->bindings, $value );
		} else {
			$this->bindings[] = $value;
		}
	}

	/**
	 * Validate the column name.
	 *
	 * @param array $columns
	 *
	 * @return void
	 */
	private function validate_columns( array $columns ): void {
		foreach ( $columns as $column ) {
			if ( '*' !== $column && ! in_array( strtolower( $column ), $this->get_column_names(), true ) ) {
				throw new InvalidArgumentException( "Column {$column} does not exist in the table." );
			}
		}
	}

	/**
	 * Validate the operator.
	 *
	 * @param string $operator
	 *
	 * @return void
	 */
	private function validate_operator( string $operator ): void {
		$valid_operators = array( '=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT' );

		if ( ! in_array( $operator, $valid_operators, true ) ) {
			throw new InvalidArgumentException( 'Invalid operator provided.' );
		}
	}

	/**
	 * Validate the logical operator.
	 *
	 * @param string $logical_operator
	 *
	 * @return void
	 */
	private function validate_logical_operator( string $logical_operator ): void {
		if ( ! in_array( strtoupper( $logical_operator ), array( 'AND', 'OR' ), true ) ) {
			throw new InvalidArgumentException( 'Logical operator must be either AND or OR.' );
		}
	}

	/**
	 * Validate the sort direction.
	 *
	 * @param string $direction
	 *
	 * @return void
	 */
	private function validate_direction( string $direction ): void {
		if ( ! in_array( strtoupper( $direction ), array( 'ASC', 'DESC' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid sort direction.' );
		}
	}

	/**
	 * Reset the query parameters.
	 *
	 * @return void
	 */
	private function reset_query(): void {
		$this->columns  = array( '*' );
		$this->wheres   = array();
		$this->bindings = array();
		$this->order_by = '';
		$this->limit    = 0;
		$this->offset   = 0;
	}

}
