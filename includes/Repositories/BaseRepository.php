<?php

namespace WPO\AOM\Repositories;

use InvalidArgumentException;
use RuntimeException;
use WPO\AOM\Models\BaseModel;

defined( 'ABSPATH' ) || exit;

abstract class BaseRepository {
	protected \wpdb $wpdb;
	private string $plugin_table_prefix = 'wpo_aom_';
	private string $table_name;
	protected bool $enable_cache = false;
	protected static array $cache = array();
	private ?string $alias = null;

	/** @var class-string<BaseModel> */
	private string $model_class;

	private array $joins = array();

	/**
	 * Each where item is an array of:
	 * [0] => string column
	 * [1] => string operator
	 * [2] => mixed value
	 * [3] => string logical operator (AND|OR)
	 *
	 * @var array<int, string|array{0:string,1:string,2:mixed,3:string}>
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
	 * Get the compiled query.
	 *
	 * @param bool $reset
	 *
	 * @return string
	 */
	public function get_query( bool $reset = false ): string {
		$columns = implode( ', ', $this->columns );
		$query   = "SELECT {$columns} FROM {$this->get_table_full_name( true )}";
		$query   = $this->append_query_clauses( $query );
		$query   = $this->append_bindings( $query );

		if ( $reset ) {
			$this->reset_query();
		}

		return $query;
	}

	/**
	 * Get records.
	 *
	 * @template TModel of BaseModel
	 * @param bool $raw Whether to return raw database results instead of model instances. Default false.
	 * @param bool $reset Whether to reset the query after execution. Default true.
	 *
	 * @return array<int, TModel>
	 */
	public function get( bool $raw = false, bool $reset = true ): array {
		$query = $this->get_query( false );

		$result = $this->wpdb->get_results( $query, ARRAY_A ) ?? array();

		if ( $reset ) {
			$this->reset_query();
		}

		return $raw ? $result : array_map( array( $this, 'map_to_model' ), $result );
	}

	/**
	 * Get first record.
	 *
	 * @template TModel of BaseModel
	 * @return TModel|null
	 */
	public function first(): ?BaseModel {
		$results = $this->limit( 1 )->get();

		return ! empty( $results ) ? reset( $results ) : null;
	}

	/**
	 * Find a record by a specific column and value.
	 *
	 * @template TModel of BaseModel
	 * @param string $column
	 * @param mixed $value
	 *
	 * @return TModel|null
	 */
	public function find_by( string $column, $value ): ?BaseModel {
		$cached = $this->get_cache( $column, $value );
		if ( $cached ) {
			return $cached;
		}

		$model = $this->where( $column, $value )->first();
		if ( $model ) {
			$this->set_cache( $column, $value, $model );
		}

		return $model;
	}

	/**
	 * Find a record by ID.
	 *
	 * @template TModel of BaseModel
	 * @param int $id Record ID.
	 *
	 * @return TModel|null
	 */
	public function find( int $id ): ?BaseModel {
		return $this->find_by( 'id', absint( $id ) );
	}

	/**
	 * Find a TaskField by its label.
	 *
	 * @template TModel of BaseModel
	 * @param string $label
	 *
	 * @return TModel|null
	 */
	public function find_by_slug( string $label ): ?BaseModel {
		return $this->find_by( 'slug', $label );
	}

	/**
	 * Find all records matching a specific column and value.
	 *
	 * @template TModel of BaseModel
	 * @param string $column
	 * @param mixed $value
	 *
	 * @return array<int, TModel>
	 */
	public function find_all_by( string $column, $value ): array {
		return $this->where( $column, $value )->get();
	}

	/**
	 * Find multiple records by their IDs.
	 *
	 * @template TModel of BaseModel
	 * @param array<int> $ids
	 *
	 * @return array<int, TModel>
	 */
	public function find_many( array $ids ): array {
		if ( empty( $ids ) ) {
			return array();
		}

		$results      = array();
		$uncached_ids = array();

		foreach ( $ids as $id ) {
			$cache = $this->get_cache( 'id', $id );
			if ( $cache ) {
				$results[] = $cache;
			} else {
				$uncached_ids[] = $id;
			}
		}

		if ( ! empty( $uncached_ids ) ) {
			$found_models = $this->where( 'id', 'IN', $uncached_ids )->get();
			foreach ( $found_models as $model ) {
				$this->set_cache( 'id', $model->id, $model );
				$results[] = $model;
			}
		}

		return $results;
	}

	/**
	 * Find all records where a column's value is in a given array.
	 *
	 * @template TModel of BaseModel
	 * @param string $column
	 * @param array<int, mixed> $value
	 *
	 * @return array<int, TModel>
	 */
	public function find_all_by_in( string $column, array $value ): array {
		if ( empty( $value ) ) {
			return array();
		}

		return $this->where( $column, 'IN', $value )->get();
	}

	/**
	 * Execute a raw query.
	 *
	 * @param string $query
	 * @param array<int, mixed> $bindings
	 * @param bool $reset
	 *
	 * @return int|bool Number of rows affected.
	 */
	public function execute_raw( string $query, array $bindings = array(), bool $reset = true ) {
		if ( ! empty( $bindings ) ) {
			$query = $this->wpdb->prepare( $query, ...array_values( $bindings ) );
		}

		$result = $this->wpdb->query( $query );

		if ( $reset ) {
			$this->reset_query();
		}

		return $result;
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

		// Convert DateTime objects to strings.
		foreach ( $data as $key => $value ) {
			if ( $value instanceof \DateTime ) {
				$data[ $key ] = $value->format( 'Y-m-d H:i:s' );
			}
		}

		$result = $this->wpdb->insert( $this->get_table_full_name(), $data );

		$this->reset_query();

		return $result ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Insert multiple records with raw columns and prepared values.
	 *
	 * @param string $columns Comma-separated column names.
	 * @param array $rows Array of placeholder strings, e.g., ['(%d, %d, %s)', '(%d, %d, %s)'].
	 * @param array $bindings Flat array of all values for prepare().
	 *
	 * @return int|false Number of affected rows or false on failure.
	 * @throws InvalidArgumentException If columns, rows, or bindings are empty.
	 */
	public function insert_raw( string $columns, array $rows, array $bindings ) {
		if ( '' === trim( $columns ) || empty( $rows ) || empty( $bindings ) ) {
			throw new InvalidArgumentException( 'Columns, rows, and bindings must not be empty.' );
		}

		$placeholders = implode( ', ', $rows );
		$query        = "INSERT INTO {$this->get_table_full_name()} ({$columns}) VALUES {$placeholders}";

		// Add bindings
		$this->bindings = array_merge( $this->bindings, $bindings );

		// Prepare query with bindings
		$query = $this->append_bindings( $query );

		$result = $this->wpdb->query( $query );

		$this->reset_query();

		return false === $result ? false : (int) $result;
	}

	/**
	 * Update records.
	 *
	 * @param array<string, mixed> $data Columns to set.
	 *
	 * @return int|false Number of rows updated, or false on error. Returns 0 if no rows were affected (data unchanged).
	 * @throws RuntimeException If no WHERE clause is specified.
	 * @throws InvalidArgumentException If data is empty or columns are invalid.
	 */
	public function update( array $data ) {
		// Validate the data array.
		if ( empty( $data ) ) {
			throw new InvalidArgumentException( 'Data must be a non-empty array.' );
		}

		// Ensure that the WHERE clause is set.
		if ( empty( $this->wheres ) ) {
			throw new RuntimeException( 'No WHERE clause specified for update.' );
		}

		$this->validate_columns( array_keys( $data ) );

		// Prepare WHERE array
		// This method compiles the WHERE clauses using the `where` method of the query builder.
		// It only works with equality checks (i.e., `=` operator), as we are using WordPress's built-in update method.
		$where = array();
		foreach ( $this->wheres as [$column, $operator, $value, $logical_operator] ) {
			if ( '=' !== $operator ) {
				throw new InvalidArgumentException( 'Only equality checks are supported in WHERE clause for update.' );
			}
			$where[ $column ] = $value;
		}

		$this->reset_query();

		return $this->wpdb->update( $this->get_table_full_name(), $data, $where );
	}

	/**
	 * Update records with a raw SET clause.
	 *
	 * @param string $set_clause Raw SET clause (e.g., "column1 = value1, column2 = value2").
	 * @param array $bindings
	 *
	 * @return int|false Number of rows updated, or false on error. Returns 0 if no rows were affected (data unchanged).
	 * @throws InvalidArgumentException If SET clause is empty.
	 * @throws RuntimeException If no WHERE clause is specified.
	 */
	public function update_raw( string $set_clause, array $bindings ) {
		// Ensure that the SET clause is not empty.
		if ( '' === trim( $set_clause ) ) {
			throw new InvalidArgumentException( 'SET clause must not be empty.' );
		}

		// Ensure that the WHERE clause is set.
		if ( empty( $this->wheres ) ) {
			throw new RuntimeException( 'No WHERE clause specified for update.' );
		}

		// Add bindings
		$this->bindings = array_merge( $this->bindings, $bindings );

		$query = "UPDATE {$this->get_table_full_name()} SET {$set_clause}";
		$query = $this->append_query_clauses( $query );
		$query = $this->append_bindings( $query );

		$this->reset_query();

		$result = $this->wpdb->query( $query );

		return false === $result ? false : (int) $result;
	}

	/**
	 * Save the model (insert or update based on presence of ID).
	 *
	 * @template TModel of BaseModel
	 * @param TModel $model
	 *
	 * @return int|false
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 */
	public function save( BaseModel $model ) {
		return $model->id
			? $this->where( 'id', $model->id )->update( $model->to_db_array() )
			: $this->insert( $model->to_db_array() );
	}

	/**
	 * Delete records from the table based on the WHERE clause.
	 *
	 * @return int|false Number of rows deleted or false on failure.
	 * @throws RuntimeException If no WHERE clause is specified.
	 * @throws InvalidArgumentException If invalid arguments are sent to where().
	 */
	public function delete( ?int $id = null ): int {
		if ( ! empty( $id ) ) {
			$this->where( 'id', absint( $id ) );
		}

		if ( empty( $this->wheres ) ) {
			throw new RuntimeException( 'No WHERE clause specified for delete.' );
		}

		// Prepare WHERE array
		// This method compiles the WHERE clauses using the `where` method of the query builder.
		// It only works with equality checks (i.e., `=` operator), as we are using WordPress's built-in update method.
		$where = array();
		foreach ( $this->wheres as [$column, $operator, $value, $logical_operator] ) {
			if ( '=' !== $operator ) {
				throw new InvalidArgumentException( 'Only equality checks are supported in WHERE clause for delete.' );
			}
			$where[ $column ] = $value;
		}

		$this->reset_query();

		return (int) $this->wpdb->delete( $this->get_table_full_name(), $where );
	}

	/**
	 * Delete records using the full query builder (supports IN, etc.).
	 *
	 * @return int|false Number of rows deleted or false on failure.
	 * @throws RuntimeException If no WHERE clause is specified.
	 */
	public function delete_raw() {
		if ( empty( $this->wheres ) ) {
			throw new RuntimeException( 'No WHERE clause specified for delete.' );
		}

		$query = "DELETE FROM {$this->get_table_full_name()}";
		$query .= $this->compile_where();
		$query = $this->append_bindings( $query );

		$result = $this->wpdb->query( $query );

		$this->reset_query();

		return $result;
	}

	/** ================================
	 *   Helpers
	 *  ================================ */

	/**
	 * Execute a callback within a database transaction.
	 *
	 * @param callable $callback
	 *
	 * @return mixed Result of the callback, or false on failure.
	 * @throws \Throwable
	 */
	public function transaction( callable $callback ) {
		$this->wpdb->query( 'START TRANSACTION' );

		try {
			$result = $callback( $this );

			if ( false === $result ) {
				$this->wpdb->query( 'ROLLBACK' );
				return false;
			}

			$this->wpdb->query( 'COMMIT' );

			return $result;

		} catch ( \Throwable $e ) {
			$this->wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}

	/**
	 * Clear the static cache.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$cache = array();
	}

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
	 * Set an alias for the table.
	 *
	 * @param string $alias
	 *
	 * @return self
	 */
	public function alias( string $alias ): self {
		$this->alias = $alias;

		return $this;
	}

	/**
	 * Get the full table name with prefix and optional alias.
	 *
	 * @param bool $with_alias
	 *
	 * @return string
	 */
	public function get_table_full_name( bool $with_alias = false ): string {
		$name = $this->wpdb->prefix . $this->plugin_table_prefix . $this->table_name;
		return $this->alias && $with_alias ? "{$name} AS {$this->alias}" : $name;
	}

	/**
	 * Select columns.
	 *
	 * @param array<int,string> $columns Columns.
	 *
	 * @return self
	 */
	public function select( array $columns ): self {
		$this->validate_columns( $columns );
		$this->columns = $columns;

		return $this;
	}

	/**
	 * Add a JOIN clause to the query.
	 *
	 * Usage examples:
	 *   ->join('values AS v', 'tasks.id', '=', 'v.task_id')
	 *   ->join('values AS v', 'tasks.id = v.task_id')
	 *
	 * @param string $table The table name (optionally with alias).
	 * @param string $first First column or full ON condition.
	 * @param string|null $operator Comparison operator or null.
	 * @param string|null $second Second column if operator provided.
	 * @param string $type Join type: INNER, LEFT, RIGHT, FULL, CROSS.
	 *
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public function join(
		string $table,
		string $first,
		?string $operator = null,
		?string $second = null,
		string $type = 'INNER'
	): self {
		$type        = strtoupper( $type );
		$valid_types = array( 'INNER', 'LEFT', 'RIGHT', 'FULL', 'CROSS' );

		if ( ! in_array( $type, $valid_types, true ) ) {
			throw new InvalidArgumentException( 'Invalid join type provided.' );
		}

		if ( $operator === null && $second === null ) {
			// Full ON condition provided in $first.
			$on_condition = $first;
		} elseif ( $operator !== null && $second !== null ) {
			// Column comparison provided.
			$on_condition = "{$first} {$operator} {$second}";
		} else {
			throw new InvalidArgumentException( 'Invalid arguments for JOIN clause.' );
		}

		$this->joins[] = "{$type} JOIN {$table} ON {$on_condition}";

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
	 * @throws InvalidArgumentException If invalid arguments are provided.
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
	 * Add a raw WHERE clause to the query.
	 *
	 * @param string $condition
	 * @param string $logical_operator
	 *
	 * @return self
	 * @throws InvalidArgumentException If invalid logical operator is provided.
	 */
	public function where_raw( string $condition, string $logical_operator = 'AND' ): self {
		$this->validate_logical_operator( $logical_operator );

		$this->wheres[] = array(
			$condition,
			'RAW',
			null,
			strtoupper( $logical_operator )
		);

		return $this;
	}

	/**
	 * Add an ORDER BY clause to the query.
	 *
	 * @param string $column
	 * @param string $direction (ASC|DESC)
	 *
	 * @return self
	 * @throws InvalidArgumentException If invalid arguments or direction are provided.
	 */
	public function order_by( string $column, string $direction = 'ASC' ): self {
		$direction = strtoupper( $direction );

		$this->validate_columns( (array) $column );
		$this->validate_direction( $direction );

		$this->order_by = " ORDER BY {$column} {$direction}";

		return $this;
	}

	/**
	 * Add a raw ORDER BY clause to the query.
	 *
	 * @param string $expression
	 * @param string $direction
	 *
	 * @return self
	 * @throws InvalidArgumentException If invalid direction is provided.
	 */
	public function order_by_raw( string $expression ): self {
		$this->order_by = " ORDER BY {$expression}";

		return $this;
	}

	/**
	 * Set a limit on the number of records returned.
	 *
	 * @param int $limit
	 *
	 * @return self
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
	 * @return self
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
		$query .= $this->compile_joins();
		$query .= $this->compile_where();
		$query .= $this->order_by;
		$query .= $this->compile_limit_offset();

		return $query;
	}

	/**
	 * Append bindings to the query using $wpdb->prepare.
	 *
	 * @param string $query
	 *
	 * @return string
	 */
	private function append_bindings( string $query ): string {
		if ( ! empty( $this->bindings ) ) {
			$query = $this->wpdb->prepare( $query, ...array_values( $this->bindings ) );
		}

		return $query;
	}

	/**
	 * Compile the JOIN clauses into a SQL string.
	 *
	 * @return string
	 */
	private function compile_joins(): string {
		if ( empty( $this->joins ) ) {
			return '';
		}

		return ' ' . implode( ' ', $this->joins ) . ' ';
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

			if ( 'RAW' === $operator ) {
				$parts[] = "{$prefix}{$column}";
				continue;
			}

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

		// If offset is set but no limit, use a very large default limit
		// This is because MySQL requires LIMIT before OFFSET
		if ( $this->offset > 0 && $this->limit === 0 ) {
			$limit_offset .= ' LIMIT 18446744073709551615'; // Max MySQL BIGINT value
		} elseif ( $this->limit > 0 ) {
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
			// Separate column name from possible alias.
			if ( false !== stripos( $column, ' AS ' ) ) {
				$parts  = preg_split( '/\s+AS\s+/i', $column );
				$column = $parts[0];
			}

			// Handle table.column format.
			if ( false !== strpos( $column, '.' ) ) {
				$parts  = explode( '.', $column );
				$column = end( $parts );
			}

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
		$this->alias    = null;
		$this->columns  = array( '*' );
		$this->joins    = array();
		$this->wheres   = array();
		$this->bindings = array();
		$this->order_by = '';
		$this->limit    = 0;
		$this->offset   = 0;
	}

	/**
	 * Get a cached model by column and value.
	 *
	 * @template TModel of BaseModel
	 * @param string $column
	 * @param mixed $value
	 *
	 * @return TModel|null
	 */
	private function get_cache( string $column, $value ): ?BaseModel {
		if ( ! $this->enable_cache ) {
			return null;
		}

		$cache_key = $this->cache_key( $column, $value );

		return self::$cache[ $cache_key ] ?? null;
	}

	/**
	 * Set a cached model by column and value.
	 *
	 * @template TModel of BaseModel
	 * @param string $column
	 * @param mixed $value
	 * @param TModel $model
	 *
	 * @return void
	 */
	private function set_cache( string $column, $value, BaseModel $model ): void {
		if ( ! $this->enable_cache ) {
			return;
		}

		$cache_key                 = $this->cache_key( $column, $value );
		self::$cache[ $cache_key ] = $model;
	}

	/**
	 * Generate a cache key for a column and value.
	 *
	 * @param string $column
	 * @param $value
	 *
	 * @return string
	 */
	private function cache_key( string $column, $value ): string {
		$class = static::class;

		return sprintf( '%s:%s:%s', $class, strtolower( $column ), (string) $value );
	}
}
