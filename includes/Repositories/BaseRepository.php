<?php

namespace WPO\OTD\Repositories;

use InvalidArgumentException;
use RuntimeException;
use WPO\OTD\Models\BaseModel;
use WPO\OTD\Utilities\Paginator;

defined( 'ABSPATH' ) || exit;

abstract class BaseRepository {
	protected \wpdb $wpdb;
	private string $plugin_table_prefix = 'wpo_otd_';
	private string $table_name;
	protected bool $enable_cache = false;
	protected static array $cache = array();
	private ?string $alias = null;

	/** @var class-string<BaseModel> */
	private string $model_class;

	private array $joins = array();

	/**
	 * Tables joined into the current query, keyed by the alias (or table name) the query refers to them by.
	 *
	 * @var array<string, string>
	 */
	private array $joined_tables = array();

	/**
	 * Each where item is an array of:
	 * [0] => string column, or for a GROUP the group's own where items
	 * [1] => string operator, RAW or GROUP
	 * [2] => mixed value
	 * [3] => string logical operator (AND|OR)
	 *
	 * @var array<int, array{0:string|array,1:string,2:mixed,3:string}>
	 */
	private array $wheres = array();

	/** @var array<int, mixed> */
	private array $bindings = array();

	private array $columns        = array( '*' );
	private string $order_by      = '';
	private int $limit            = 0;
	private int $offset           = 0;
	private bool $lock_for_update = false;

	/**
	 * Primary key column, used as the fallback ORDER BY for stable pagination.
	 * Override in child repositories whose key differs.
	 *
	 * @var string
	 */
	protected string $primary_key = 'id';

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
		$query  .= $this->lock_for_update ? ' FOR UPDATE' : '';
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

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared inside append_bindings().
		$result = $this->run_query( fn() => $this->wpdb->get_results( $query, ARRAY_A ) ) ?? array();

		if ( $reset ) {
			$this->reset_query();
		}

		return $raw ? $result : array_map( array( $this, 'map_to_model' ), $result );
	}

	/**
	 * Count rows matching the current query (joins + where), ignoring ORDER BY / LIMIT / OFFSET.
	 *
	 * @param bool $reset
	 *
	 * @return int
	 */
	public function count( bool $reset = true ): int {
		$query  = "SELECT COUNT(*) FROM {$this->get_table_full_name( true )}";
		$query .= $this->compile_joins();
		$query .= $this->compile_where();
		$query  = $this->append_bindings( $query );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared inside append_bindings().
		$total = (int) $this->run_query( fn() => $this->wpdb->get_var( $query ) );

		if ( $reset ) {
			$this->reset_query();
		}

		return $total;
	}

	/**
	 * The highest value of a column among the rows matching the current query (joins + where), ignoring ORDER BY /
	 * LIMIT / OFFSET.
	 *
	 * @param string $column
	 *
	 * @return string|null As the database gives it. Null when no row matches, when the column is NULL in every one,
	 *                     or when the database failed; get_last_db_error() tells them apart.
	 * @throws InvalidArgumentException If the column is not one of the table's.
	 */
	public function max( string $column ): ?string {
		$this->validate_columns( array( $column ) );

		$query  = "SELECT MAX({$column}) FROM {$this->get_table_full_name( true )}";
		$query .= $this->compile_joins();
		$query .= $this->compile_where();
		$query  = $this->append_bindings( $query );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared inside append_bindings().
		$max = $this->run_query( fn() => $this->wpdb->get_var( $query ) );

		$this->reset_query();

		return null === $max ? null : (string) $max;
	}

	/**
	 * The highest value of a column for each value of another, among the rows matching the current query
	 * (joins + where), ignoring ORDER BY / LIMIT / OFFSET.
	 *
	 * @param string $group_column The column to group by.
	 * @param string $column       The column whose highest value is read.
	 *
	 * @return array<int|string, string>|null Each group's value => its highest value, as the database gives them,
	 *                                        leaving out a group whose column is NULL in every row. Null when the
	 *                                        database failed.
	 * @throws InvalidArgumentException If either column is not one of the table's.
	 */
	public function max_by( string $group_column, string $column ): ?array {
		$this->validate_columns( array( $group_column, $column ) );

		$query  = "SELECT {$group_column} AS group_value, MAX({$column}) AS max_value FROM {$this->get_table_full_name( true )}";
		$query .= $this->compile_joins();
		$query .= $this->compile_where();
		$query .= " GROUP BY {$group_column}";
		$query  = $this->append_bindings( $query );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared inside append_bindings().
		$rows = $this->run_query( fn() => $this->wpdb->get_results( $query, ARRAY_A ) );

		$this->reset_query();

		// $wpdb gives an empty list for a failed query too, so only its error tells that apart from no rows.
		if ( ! is_array( $rows ) || '' !== $this->get_last_db_error() ) {
			return null;
		}

		$max = array();

		foreach ( $rows as $row ) {
			if ( null !== $row['max_value'] ) {
				$max[ $row['group_value'] ] = (string) $row['max_value'];
			}
		}

		return $max;
	}

	/**
	 * Paginate the current query.
	 *
	 * @param int  $per_page Items per page.
	 * @param int  $page     Page number (1-based).
	 * @param bool $raw      Whether the page items are raw rows instead of models. Default false.
	 *
	 * @return Paginator
	 */
	public function paginate( int $per_page, int $page = 1, bool $raw = false ): Paginator {
		$per_page = max( 1, $per_page );
		$page     = max( 1, $page );

		$total = $this->count( false );

		// LIMIT/OFFSET paging needs a deterministic ORDER BY, otherwise rows can
		// repeat or be skipped across pages. Fall back to the primary key when the
		// caller hasn't set one.
		if ( '' === $this->order_by ) {
			$this->order_by( $this->primary_key );
		}

		$items = $this->limit( $per_page )
			->offset( ( $page - 1 ) * $per_page )
			->get( $raw );

		return new Paginator( $items, $total, $per_page, $page );
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
		// A locking read must reach the database: a cached model would take no lock and could be stale.
		$cached = $this->lock_for_update ? null : $this->get_cache( $column, $value );
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
	 * Find a TaskField by its slug.
	 *
	 * @template TModel of BaseModel
	 * @param string $slug
	 *
	 * @return TModel|null
	 */
	public function find_by_slug( string $slug ): ?BaseModel {
		return $this->find_by( 'slug', $slug );
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
	public function execute_raw( string $query, array $bindings = array(), bool $reset = true ): bool|int {
		if ( ! empty( $bindings ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query is prepared on this line; placeholders are caller-supplied.
			$query = $this->wpdb->prepare( $query, ...array_values( $bindings ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above.
		$result = $this->run_query( fn() => $this->wpdb->query( $query ) );

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
	 * @throws InvalidArgumentException If data is empty, or a key is not a bare name of this table's columns.
	 */
	public function insert( array $data ): int|false {
		if ( empty( $data ) ) {
			throw $this->discard( new InvalidArgumentException( 'Data must be a non-empty array.' ) );
		}

		$this->validate_set_columns( array_keys( $data ) );

		// Convert DateTime objects to strings.
		foreach ( $data as $key => $value ) {
			if ( $value instanceof \DateTime ) {
				$data[ $key ] = $value->format( 'Y-m-d H:i:s' );
			}
		}

		$result = $this->run_query( fn() => $this->wpdb->insert( $this->get_table_full_name(), $data ) );

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
	protected function insert_raw( string $columns, array $rows, array $bindings ): int|false {
		if ( '' === trim( $columns ) || empty( $rows ) || empty( $bindings ) ) {
			throw $this->discard( new InvalidArgumentException( 'Columns, rows, and bindings must not be empty.' ) );
		}

		$placeholders = implode( ', ', $rows );
		$query        = "INSERT INTO {$this->get_table_full_name()} ({$columns}) VALUES {$placeholders}";

		// Add bindings
		$this->bindings = array_merge( $this->bindings, $bindings );

		// Prepare query with bindings
		$query = $this->append_bindings( $query );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above.
		$result = $this->run_query( fn() => $this->wpdb->query( $query ) );

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
	 * @throws InvalidArgumentException If data is empty, a key is not a bare name of this table's columns, or a WHERE
	 *                                  clause is not "column = value" joined by AND: another operator, an OR, a
	 *                                  group, a column compared twice, or a name that is not bare.
	 */
	public function update( array $data ): int|false {
		// Validate the data array.
		if ( empty( $data ) ) {
			throw $this->discard( new InvalidArgumentException( 'Data must be a non-empty array.' ) );
		}

		// Ensure that the WHERE clause is set.
		if ( empty( $this->wheres ) ) {
			throw $this->discard( new RuntimeException( 'No WHERE clause specified for update.' ) );
		}

		$this->validate_set_columns( array_keys( $data ) );

		$where = $this->equality_where_array( 'update' );

		$this->reset_query();

		return $this->wpdb->update( $this->get_table_full_name(), $data, $where );
	}

	/**
	 * Set columns of the rows matching the WHERE clauses, whatever they are.
	 *
	 * Unlike update(), every kind of WHERE clause works here (any operator, OR, groups), so a row can be checked and
	 * changed in one atomic statement: the database re-checks the conditions on the latest version of each row it
	 * changes.
	 *
	 * @param array<string, mixed> $data Columns to set, to their values. Null sets NULL.
	 *
	 * @return int|false Number of rows changed, or false on error. A row that already held the values is not counted.
	 * @throws RuntimeException If no WHERE clause is specified.
	 * @throws InvalidArgumentException If data is empty, or a key is not a bare name of this table's columns.
	 */
	public function update_matching( array $data ): int|false {
		if ( empty( $data ) ) {
			throw $this->discard( new InvalidArgumentException( 'Data must be a non-empty array.' ) );
		}

		$this->validate_set_columns( array_keys( $data ) );

		[ $sets, $bindings ] = $this->compile_sets( $data );

		return $this->update_raw( implode( ', ', $sets ), $bindings );
	}

	/**
	 * Add to a numeric column of the matching rows, setting other columns in the same statement.
	 *
	 * Like update_matching(), every kind of WHERE clause works here.
	 *
	 * @param string               $column Column to add to.
	 * @param int                  $amount Amount to add; a negative amount subtracts.
	 * @param array<string, mixed> $extra  Other columns to set, to their values. Null sets NULL.
	 *
	 * @return int|false Number of rows changed, or false on error.
	 * @throws RuntimeException If no WHERE clause is specified.
	 * @throws InvalidArgumentException If a column is not a bare name of this table's columns.
	 */
	public function increment( string $column, int $amount = 1, array $extra = array() ): int|false {
		$this->validate_set_columns( array_merge( array( $column ), array_keys( $extra ) ) );

		$column = strtolower( $column );

		[ $sets, $bindings ] = $this->compile_sets( $extra );

		return $this->update_raw(
			implode( ', ', array_merge( array( "{$column} = {$column} + %d" ), $sets ) ),
			array_merge( array( $amount ), $bindings )
		);
	}

	/**
	 * Turn columns and their values into SET assignments and the values they bind, in order.
	 *
	 * The columns must already be validated as bare names of this table's columns.
	 *
	 * @param array<string, mixed> $data Columns to set, to their values. Null is written as NULL, with no binding.
	 *
	 * @return array{0: string[], 1: array<int, mixed>}
	 */
	private function compile_sets( array $data ): array {
		$sets     = array();
		$bindings = array();

		foreach ( $data as $name => $value ) {
			$name = strtolower( $name );

			if ( null === $value ) {
				$sets[] = "{$name} = NULL";
				continue;
			}

			if ( $value instanceof \DateTime ) {
				$value = $value->format( 'Y-m-d H:i:s' );
			}

			$sets[]     = "{$name} = {$this->placeholder_for( $value )}";
			$bindings[] = $value;
		}

		return array( $sets, $bindings );
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
	protected function update_raw( string $set_clause, array $bindings ): int|false {
		// Ensure that the SET clause is not empty.
		if ( '' === trim( $set_clause ) ) {
			throw $this->discard( new InvalidArgumentException( 'SET clause must not be empty.' ) );
		}

		// Ensure that the WHERE clause is set.
		if ( empty( $this->wheres ) ) {
			throw $this->discard( new RuntimeException( 'No WHERE clause specified for update.' ) );
		}

		// The SET placeholders come before the WHERE ones in the query, so their bindings go first.
		$this->bindings = array_merge( $bindings, $this->bindings );

		$query = "UPDATE {$this->get_table_full_name()} SET {$set_clause}";
		$query = $this->append_query_clauses( $query );
		$query = $this->append_bindings( $query );

		$this->reset_query();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above.
		$result = $this->run_query( fn() => $this->wpdb->query( $query ) );

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
	public function save( BaseModel $model ): int|false {
		return $model->id
			? $this->where( 'id', $model->id )->update( $model->to_db_array() )
			: $this->insert( $model->to_db_array() );
	}

	/**
	 * The last MySQL error string from the underlying $wpdb, if any.
	 *
	 * @return string Empty string when there was no error.
	 */
	public function get_last_db_error(): string {
		return (string) $this->wpdb->last_error;
	}

	/**
	 * Delete records from the table based on the WHERE clause.
	 *
	 * @return int|false Number of rows deleted or false on failure.
	 * @throws RuntimeException If no WHERE clause is specified.
	 * @throws InvalidArgumentException If invalid arguments are sent to where(), or a WHERE clause is not
	 *                                  "column = value" joined by AND: another operator, an OR, a group, a column
	 *                                  compared twice (the id included), or a name that is not bare.
	 */
	public function delete( ?int $id = null ): int|false {
		if ( ! empty( $id ) ) {
			$this->where( 'id', absint( $id ) );
		}

		if ( empty( $this->wheres ) ) {
			throw $this->discard( new RuntimeException( 'No WHERE clause specified for delete.' ) );
		}

		$where = $this->equality_where_array( 'delete' );

		$this->reset_query();

		return $this->wpdb->delete( $this->get_table_full_name(), $where );
	}

	/**
	 * Delete records using the full query builder (supports IN, etc.).
	 *
	 * @return int|false Number of rows deleted or false on failure.
	 * @throws RuntimeException If no WHERE clause is specified.
	 */
	public function delete_raw(): int|false {
		if ( empty( $this->wheres ) ) {
			throw $this->discard( new RuntimeException( 'No WHERE clause specified for delete.' ) );
		}

		$query = "DELETE FROM {$this->get_table_full_name()}";
		$query .= $this->compile_where();
		$query = $this->append_bindings( $query );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above.
		$result = $this->run_query( fn() => $this->wpdb->query( $query ) );

		$this->reset_query();

		return $result;
	}

	/** ================================
	 *   Helpers
	 *  ================================ */

	/**
	 * Execute a callback within a database transaction.
	 *
	 * On failure, get_last_db_error() still reports the error that made it fail, not the rollback's.
	 *
	 * A transaction the database cannot start runs nothing, since its writes would not be atomic. One it cannot
	 * commit is rolled back. Both fail like a callback that returns false.
	 *
	 * So does one whose connection was lost and reopened before it committed, which shows as a different connection
	 * after the COMMIT than at the start. $wpdb reconnects when the server has gone away and runs the statement again
	 * on the new connection, where the transaction no longer exists: the writes made before the reconnect are lost
	 * with the old connection, and the statement run again and every one after it, the COMMIT included, run in
	 * autocommit and are already kept. The rollback cannot undo those, so such a transaction is not all or nothing:
	 * it fails, and its caller must treat its writes as unknown. Only turning off $wpdb's retry would close that gap.
	 *
	 * The connection is compared only after the COMMIT, which always goes to the connection the START went to:
	 * database drop-ins that pick a connection per query may send a read inside the callback to another one.
	 *
	 * @param callable $callback
	 *
	 * @return mixed Result of the callback, or false on failure.
	 * @throws \Throwable
	 */
	public function transaction( callable $callback ): mixed {
		if ( false === $this->wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}

		// Held, not just identified, so the object cannot be freed and its identity reused by a later connection.
		$connection = $this->connection();

		try {
			$result = $callback( $this );

			if ( false === $result ) {
				$this->rollback();
				return false;
			}

			if ( false === $this->wpdb->query( 'COMMIT' ) ) {
				$this->rollback();
				return false;
			}

			if ( $this->connection() !== $connection ) {
				return $this->fail_reconnected();
			}

			return $result;

		} catch ( \Throwable $e ) {
			$this->rollback();
			throw $e;
		}
	}

	/**
	 * The database connection $wpdb is using now. A reconnect replaces it with a new one.
	 *
	 * @return mixed The connection handle, or null when $wpdb has none.
	 */
	private function connection(): mixed {
		return $this->wpdb->dbh ?? null;
	}

	/**
	 * Fail a transaction whose connection was lost and reopened before it committed.
	 *
	 * @return false
	 */
	private function fail_reconnected(): bool {
		$this->rollback();

		$this->wpdb->last_error = 'The database connection was lost and reopened during the transaction, so its writes may be partly kept.';

		return false;
	}

	/**
	 * Roll the open transaction back, keeping the error that caused it.
	 *
	 * Every $wpdb query resets last_error, so the ROLLBACK itself would otherwise erase it.
	 *
	 * @return void
	 */
	private function rollback(): void {
		$error = $this->wpdb->last_error;

		$this->wpdb->query( 'ROLLBACK' );

		$this->wpdb->last_error = $error;
	}

	/**
	 * Clear the static cache.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		$prefix = static::class . ':';

		foreach ( array_keys( self::$cache ) as $key ) {
			if ( str_starts_with( $key, $prefix ) ) {
				unset( self::$cache[ $key ] );
			}
		}
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
	 * Get column names from a table.
	 * This caches the column names to avoid repeated queries.
	 *
	 * @param string|null $table Full table name. Defaults to this repository's table.
	 *
	 * @return array
	 */
	protected function get_column_names( ?string $table = null ): array {
		$table ??= $this->get_table_full_name();

		if ( isset( self::$column_names[ $table ] ) ) {
			return self::$column_names[ $table ];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names cannot be parameterized; this one is the repository's own or one passed to join() by plugin code.
		$columns = (array) $this->run_query( fn() => $this->wpdb->get_col( "DESCRIBE {$table}" ) );
		$columns = array_map( 'strtolower', $columns );

		self::$column_names[ $table ] = $columns;

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
			throw $this->discard( new InvalidArgumentException( 'Invalid join type provided.' ) );
		}

		if ( $operator === null && $second === null ) {
			// Full ON condition provided in $first.
			$on_condition = $first;
		} elseif ( $operator !== null && $second !== null ) {
			// Column comparison provided.
			$this->validate_operator( $operator );
			$on_condition = "{$first} {$operator} {$second}";
		} else {
			throw $this->discard( new InvalidArgumentException( 'Invalid arguments for JOIN clause.' ) );
		}

		$this->joins[] = "{$type} JOIN {$table} ON {$on_condition}";

		// Remember which table the alias refers to. Anything other than "table", "table alias" or
		// "table AS alias" (a subquery, say) is not recorded: columns qualified with it are checked
		// against this repository's table, as before.
		if ( preg_match( '/^`?([\w$]+)`?(?:\s+(?:AS\s+)?`?([\w$]+)`?)?$/i', trim( $table ), $matches ) ) {
			$this->joined_tables[ $matches[2] ?? $matches[1] ] = $matches[1];
		}

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
				throw $this->discard( new InvalidArgumentException( esc_html( "Cannot use NULL with operator $operator" ) ) );
			}

			// Add IS NULL / IS NOT NULL as a raw clause (no placeholder or binding needed).
			$this->wheres[] = array(
				strtolower( $column ) . ' ' . strtoupper( $operator ) . ' NULL',
				'RAW',
				null,
				strtoupper( $logical_operator )
			);

			return $this;
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
	protected function where_raw( string $condition, string $logical_operator = 'AND' ): self {
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
	 * Add a parenthesised group of WHERE clauses, such as "a = 1 AND ( b = 2 OR c = 3 )".
	 *
	 * The callback adds the group's clauses with where(), where_group() or where_raw() on the query it is given.
	 * A group the callback leaves empty adds nothing.
	 *
	 * @param callable $callback         Receives this query and adds the group's clauses.
	 * @param string   $logical_operator How the group joins the clauses before it: AND|OR (default AND).
	 *
	 * @return self
	 * @throws InvalidArgumentException If invalid logical operator is provided.
	 */
	public function where_group( callable $callback, string $logical_operator = 'AND' ): self {
		$this->validate_logical_operator( $logical_operator );

		// The group's clauses are collected on their own, then put back as one item in place of them.
		$outer        = $this->wheres;
		$this->wheres = array();

		try {
			$callback( $this );
		} catch ( \Throwable $exception ) {
			throw $this->discard( $exception );
		}

		$group        = $this->wheres;
		$this->wheres = $outer;

		if ( ! empty( $group ) ) {
			$this->wheres[] = array( $group, 'GROUP', null, strtoupper( $logical_operator ) );
		}

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
	 *
	 * @return self
	 */
	protected function order_by_raw( string $expression ): self {
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

	/**
	 * Lock the rows this query reads until the transaction ends, so no other transaction can change them in
	 * between (SELECT ... FOR UPDATE). Only meaningful inside transaction().
	 *
	 * @return self
	 */
	public function lock_for_update(): self {
		$this->lock_for_update = true;

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
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $query is prepared on this line; placeholders are added in where() and join() methods.
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

		return ' WHERE ' . $this->compile_conditions( $this->wheres );
	}

	/**
	 * Compile where items into SQL conditions, a group's in parentheses.
	 *
	 * @param array $wheres Where items.
	 *
	 * @return string
	 */
	private function compile_conditions( array $wheres ): string {
		$parts = array();

		foreach ( $wheres as $index => $where ) {
			[ $column, $operator, $value, $logical_operator ] = $where;

			$prefix = ( 0 === $index ) ? '' : " {$logical_operator} ";

			if ( 'GROUP' === $operator ) {
				$parts[] = "{$prefix}( {$this->compile_conditions( $column )} )";
				continue;
			}

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

		return implode( ' ', $parts );
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

			// Handle table.column format. A qualifier naming a joined table is checked against that table.
			$table = null;

			if ( str_contains( $column, '.' ) ) {
				$parts     = explode( '.', $column );
				$column    = array_pop( $parts );
				$qualifier = end( $parts );
				$table     = $this->joined_tables[ $qualifier ] ?? null;
			}

			if ( '*' !== $column && ! in_array( strtolower( $column ), $this->get_column_names( $table ), true ) ) {
				throw $this->discard( new InvalidArgumentException( esc_html( "Column $column does not exist in the table." ) ) );
			}
		}
	}

	/**
	 * Turn the WHERE clauses into the column => value array $wpdb->update() and $wpdb->delete() take.
	 *
	 * That array can only say "every column equals its value", so any clause it cannot say exactly is refused rather
	 * than dropped or changed: an operator other than =, an OR, a group, or a column compared twice. Each would
	 * otherwise make the statement match other rows than the query asked for. $wpdb writes each key into the SQL
	 * as a quoted name, so only a bare column name is passed on.
	 *
	 * @param string $statement update | delete, for the error message.
	 *
	 * @return array<string, mixed>
	 * @throws InvalidArgumentException If a clause cannot be written as column = value joined by AND.
	 */
	private function equality_where_array( string $statement ): array {
		$where = array();

		foreach ( $this->wheres as $index => [$column, $operator, $value, $logical_operator] ) {
			if ( '=' !== $operator ) {
				throw $this->discard( new InvalidArgumentException( esc_html( "Only equality checks are supported in WHERE clause for {$statement}." ) ) );
			}

			$this->validate_bare_column_names( array( $column ), "compared in WHERE clause for {$statement}" );

			// The first clause's logical operator joins it to nothing, so only the later ones matter.
			if ( 0 < $index && 'AND' !== $logical_operator ) {
				throw $this->discard( new InvalidArgumentException( esc_html( "Only AND is supported in WHERE clause for {$statement}." ) ) );
			}

			if ( array_key_exists( $column, $where ) ) {
				throw $this->discard( new InvalidArgumentException( esc_html( "Column {$column} is compared more than once in WHERE clause for {$statement}." ) ) );
			}

			$where[ $column ] = $value;
		}

		return $where;
	}

	/**
	 * Validate the columns a statement writes.
	 *
	 * A write names this table's own columns only, so a qualified name, an alias or * is rejected even where
	 * a SELECT would accept it.
	 *
	 * @param array $columns
	 *
	 * @return void
	 */
	private function validate_set_columns( array $columns ): void {
		$this->validate_bare_column_names( $columns, 'set' );
		$this->validate_columns( $columns );
	}

	/**
	 * Reject anything but bare column names: letters, digits and underscores only.
	 *
	 * validate_columns() checks a name only up to its table qualifier or alias, so whatever surrounds the name
	 * would otherwise reach the SQL unchecked.
	 *
	 * @param array  $columns
	 * @param string $use     What the column is used for, completing "cannot be ...".
	 *
	 * @return void
	 * @throws InvalidArgumentException If a column is not a bare name.
	 */
	private function validate_bare_column_names( array $columns, string $use ): void {
		foreach ( $columns as $column ) {
			if ( ! is_string( $column ) || ! preg_match( '/^\w+$/', $column ) ) {
				throw $this->discard( new InvalidArgumentException( esc_html( "Column $column cannot be {$use}: only a bare column name can." ) ) );
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
			throw $this->discard( new InvalidArgumentException( 'Invalid operator provided.' ) );
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
			throw $this->discard( new InvalidArgumentException( 'Logical operator must be either AND or OR.' ) );
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
			throw $this->discard( new InvalidArgumentException( 'Invalid sort direction.' ) );
		}
	}

	/**
	 * Run a $wpdb call, discarding the half-built query if it throws.
	 *
	 * @param callable $query
	 *
	 * @return mixed What the call returned.
	 */
	private function run_query( callable $query ): mixed {
		try {
			return $query();
		} catch ( \Throwable $exception ) {
			throw $this->discard( $exception );
		}
	}

	/**
	 * Drop the half-built query before an exception leaves the builder.
	 *
	 * Repositories are shared through the container, so a query abandoned halfway would otherwise
	 * leak its joins and conditions into the next query run on the same instance.
	 *
	 * @param \Throwable $exception
	 *
	 * @return \Throwable The same exception, to throw.
	 */
	private function discard( \Throwable $exception ): \Throwable {
		$this->reset_query();

		return $exception;
	}

	/**
	 * Reset the query parameters.
	 *
	 * @return void
	 */
	private function reset_query(): void {
		$this->alias           = null;
		$this->columns         = array( '*' );
		$this->joins           = array();
		$this->joined_tables   = array();
		$this->wheres          = array();
		$this->bindings        = array();
		$this->order_by        = '';
		$this->limit           = 0;
		$this->offset          = 0;
		$this->lock_for_update = false;
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
	 * @param mixed $value
	 *
	 * @return string
	 */
	private function cache_key( string $column, $value ): string {
		$class = static::class;

		return sprintf( '%s:%s:%s', $class, strtolower( $column ), (string) $value );
	}
}
