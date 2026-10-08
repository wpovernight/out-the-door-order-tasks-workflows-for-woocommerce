<?php

namespace WPO\OTD\Tests;

use WP_UnitTestCase;
use WPO\OTD\Core\Container\Container;
use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Repositories\TaskFieldOptionRepository;
use WPO\OTD\Repositories\TaskFieldRepository;
use WPO\OTD\Tests\Support\InstalledPlugins;

/**
 * Base class for tests that run against WordPress, WooCommerce and a real database.
 *
 * WordPress wraps each test in a transaction and rolls it back afterwards. The plugin's
 * repositories open their own transactions, and in MySQL a new START TRANSACTION commits the
 * open one, which would let rows outlive the test. While a test runs, the plugin's
 * START TRANSACTION / COMMIT / ROLLBACK are therefore rewritten into savepoints inside
 * WordPress's transaction: the plugin keeps its all-or-nothing behavior, and everything a test
 * writes is still rolled back.
 *
 * Any other statement that would commit or end that transaction (a COMMIT outside a plugin
 * transaction, DDL, SET autocommit, ...) fails the test instead of silently leaking its data,
 * unless the test called allow_real_ddl().
 */
abstract class IntegrationTestCase extends WP_UnitTestCase {
	/**
	 * Statements that make MySQL commit the open transaction.
	 *
	 * CREATE / DROP TEMPORARY TABLE are fine: WordPress rewrites the tables a test creates into those.
	 */
	private const IMPLICIT_COMMIT = '/^(ALTER|CREATE(?! TEMPORARY)|DROP(?! TEMPORARY)|RENAME|TRUNCATE|LOCK TABLES?|UNLOCK TABLES?|SET (SESSION |@@)?AUTOCOMMIT|ANALYZE|OPTIMIZE|REPAIR|GRANT|REVOKE)\b/';

	/**
	 * Savepoints standing in for the plugin's open transactions, innermost last.
	 *
	 * @var string[]
	 */
	private array $savepoints = array();

	/**
	 * Counter keeping savepoint names unique.
	 *
	 * @var int
	 */
	private static int $savepoint_count = 0;

	/**
	 * Container the current test resolves services from.
	 *
	 * @var Container|null
	 */
	private ?Container $container = null;

	/**
	 * $wpdb's error suppression before fail_queries() turned it on, or null when untouched.
	 *
	 * @var bool|null
	 */
	private ?bool $suppressed_errors = null;

	/**
	 * Whether this test may run statements that commit its transaction, see allow_real_ddl().
	 *
	 * @var bool
	 */
	private bool $real_ddl_allowed = false;

	/**
	 * Start the test with empty repository caches and the plugin's transactions nested.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		self::clear_repository_caches();
		InstalledPlugins::include_this_plugin();

		add_filter( 'query', array( $this, 'nest_plugin_transactions' ) );
	}

	/**
	 * Undo the transaction rewrite before WordPress rolls the test back.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $wpdb;

		remove_filter( 'query', array( $this, 'nest_plugin_transactions' ) );

		if ( null !== $this->suppressed_errors ) {
			$wpdb->suppress_errors( $this->suppressed_errors );
			$this->suppressed_errors = null;
		}

		$this->savepoints       = array();
		$this->container        = null;
		$this->real_ddl_allowed = false;

		parent::tear_down();

		// Models cached during the test describe rows that were just rolled back.
		self::clear_repository_caches();
	}

	/**
	 * Rewrite the plugin's transaction statements into savepoints, and refuse anything else that
	 * would commit the test's transaction.
	 *
	 * Only runs while a test does: WordPress opens its transaction before set_up() adds this
	 * filter and rolls it back after tear_down() removes it.
	 *
	 * @param string $query
	 *
	 * @return string
	 * @throws \LogicException When the statement would commit the test's transaction.
	 */
	public function nest_plugin_transactions( $query ) {
		$statement = strtoupper( preg_replace( '/\s+/', ' ', rtrim( trim( (string) $query ), "; \t\n\r" ) ) );

		if ( preg_match( '/^(START TRANSACTION|BEGIN)( WORK)?$/', $statement ) ) {
			$savepoint          = 'wpo_otd_test_' . ++self::$savepoint_count;
			$this->savepoints[] = $savepoint;

			return "SAVEPOINT {$savepoint}";
		}

		$ends_transaction = preg_match( '/^(COMMIT|ROLLBACK)( WORK)?$/', $statement, $matches );

		if ( $ends_transaction && $this->savepoints ) {
			$savepoint = array_pop( $this->savepoints );

			return 'COMMIT' === $matches[1] ? "RELEASE SAVEPOINT {$savepoint}" : "ROLLBACK TO SAVEPOINT {$savepoint}";
		}

		if ( ! $this->real_ddl_allowed && ( $ends_transaction || preg_match( self::IMPLICIT_COMMIT, $statement ) ) ) {
			throw new \LogicException(
				"This statement would commit the test's transaction and leak its data into later tests: {$query}\n"
				. 'Call allow_real_ddl() if the test really needs it, and restore the database before the test ends.'
			);
		}

		return $query;
	}

	/**
	 * Let this test run real CREATE TABLE and DROP TABLE statements, and other DDL.
	 *
	 * WordPress rewrites CREATE / DROP TABLE into temporary tables, which cannot hold foreign keys.
	 * Real DDL commits the test's transaction, so whatever the test changed outlives it: a test that
	 * calls this must restore the database itself (see InstallerTest).
	 *
	 * @return void
	 */
	protected function allow_real_ddl(): void {
		$this->real_ddl_allowed = true;

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
	}

	/**
	 * Make the database reject every query matching a pattern, for the rest of the test.
	 *
	 * The query is swapped for one that fails, so $wpdb reports the failure the usual way
	 * (false or null, plus last_error). Error output is silenced until the test ends.
	 *
	 * @param string $pattern Regular expression matched against the SQL.
	 *
	 * @return void
	 */
	protected function fail_queries( string $pattern ): void {
		global $wpdb;

		$this->suppressed_errors ??= $wpdb->suppress_errors( true );

		add_filter(
			'query',
			static fn( $query ) => preg_match( $pattern, (string) $query ) ? 'SELECT * FROM `wpo_otd_forced_failure`' : $query,
			5
		);
	}

	/**
	 * Throw from $wpdb on every query matching a pattern, for the rest of the test.
	 *
	 * @param string     $pattern   Regular expression matched against the SQL.
	 * @param \Throwable $exception
	 *
	 * @return void
	 */
	protected function throw_on_queries( string $pattern, \Throwable $exception ): void {
		add_filter(
			'query',
			static function ( $query ) use ( $pattern, $exception ) {
				if ( preg_match( $pattern, (string) $query ) ) {
					throw $exception;
				}

				return $query;
			},
			5
		);
	}

	/**
	 * Resolve a service from a container that is fresh for each test.
	 *
	 * The plugin's own container lives for the whole run, and several services cache what they read
	 * (settings, custom statuses). Resolving from a fresh container keeps that state out of the test.
	 *
	 * @template T of object
	 * @param class-string<T> $id
	 *
	 * @return T
	 */
	protected function make( string $id ): object {
		$this->container ??= new Container();

		return $this->container->get( $id );
	}

	/**
	 * Get the full name of one of the plugin's tables.
	 *
	 * @param string $table Table name without the $wpdb prefix and the plugin prefix.
	 *
	 * @return string
	 */
	protected function table( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . 'wpo_otd_' . $table;
	}

	/**
	 * Get the ID of one of the seeded status options ("not_started", "in_progress", "done").
	 *
	 * @param string $slug
	 *
	 * @return int
	 */
	protected function status_option_id( string $slug ): int {
		return $this->option_id( DefaultTaskFields::STATUS, $slug );
	}

	/**
	 * Get the ID of one of the seeded priority options ("low", "medium", "high", "critical").
	 *
	 * @param string $slug
	 *
	 * @return int
	 */
	protected function priority_option_id( string $slug ): int {
		return $this->option_id( DefaultTaskFields::PRIORITY, $slug );
	}

	/**
	 * Get the ID of a field option by its field and slug.
	 *
	 * @param int    $field_id
	 * @param string $slug
	 *
	 * @return int
	 */
	protected function option_id( int $field_id, string $slug ): int {
		global $wpdb;

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table( 'task_field_options' )} WHERE field_id = %d AND slug = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$field_id,
				$slug
			)
		);

		$this->assertNotNull( $id, "No option \"{$slug}\" on field {$field_id}." );

		return (int) $id;
	}

	/**
	 * Get all values stored for one field of a task.
	 *
	 * @param int $task_id
	 * @param int $field_id
	 *
	 * @return array<int, string|null>
	 */
	protected function field_values( int $task_id, int $field_id ): array {
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT value FROM {$this->table( 'task_field_values' )} WHERE task_id = %d AND field_id = %d ORDER BY id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$task_id,
				$field_id
			)
		);
	}

	/**
	 * Get the single value stored for one field of a task, or null when there is none.
	 *
	 * @param int $task_id
	 * @param int $field_id
	 *
	 * @return string|null
	 */
	protected function field_value( int $task_id, int $field_id ): ?string {
		$values = $this->field_values( $task_id, $field_id );

		return $values[0] ?? null;
	}

	/**
	 * Count the rows of one of the plugin's tables.
	 *
	 * @param string $table Table name without the $wpdb prefix and the plugin prefix.
	 *
	 * @return int
	 */
	protected function count_rows( string $table ): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table( $table )}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Log in as a fresh user with the given role.
	 *
	 * @param string $role
	 *
	 * @return int The user ID.
	 */
	protected function act_as( string $role ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Create an order with one simple product line per given quantity.
	 *
	 * @param int[] $quantities
	 *
	 * @return \WC_Order
	 */
	protected function create_order( array $quantities = array( 1 ) ): \WC_Order {
		$order = wc_create_order();

		foreach ( $quantities as $index => $quantity ) {
			$product = new \WC_Product_Simple();
			$product->set_name( 'Product ' . ( $index + 1 ) );
			$product->set_regular_price( '10' );
			$product->save();

			$order->add_product( $product, $quantity );
		}

		$order->set_billing_first_name( 'Jane' );
		$order->set_billing_last_name( 'Doe' );
		$order->calculate_totals();
		$order->save();

		return $order;
	}

	/**
	 * Empty the static model caches of the repositories that cache.
	 *
	 * @return void
	 */
	protected static function clear_repository_caches(): void {
		TaskFieldRepository::clear_cache();
		TaskFieldOptionRepository::clear_cache();
	}
}
