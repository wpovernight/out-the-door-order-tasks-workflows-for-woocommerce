<?php

namespace WPO\OTD\Core;

use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\OutTheDoor;
use WPO\OTD\Repositories\BaseRepository;
use WPO\OTD\Repositories\TaskFieldOptionRepository;
use WPO\OTD\Repositories\TaskFieldRepository;
use WPO\OTD\Services\TaskStatusRoleService;

defined( 'ABSPATH' ) || exit;

final class Installer {
	private const OPTION_VERSION      = 'wpo_otd_version';
	private const OPTION_DB_VERSION   = 'wpo_otd_db_version';
	private const OPTION_UPGRADE_LOCK = 'wpo_otd_upgrade_lock';

	/**
	 * The install-state options this plugin writes to the wp_options table.
	 *
	 * @var string[]
	 */
	public const OPTION_NAMES = array(
		self::OPTION_VERSION,
		self::OPTION_DB_VERSION,
		self::OPTION_UPGRADE_LOCK,
	);

	/**
	 * Current database schema version.
	 *
	 * Deliberately decoupled from the plugin's version. Bump this by one whenever you add a migration.
	 */
	private const DB_VERSION = 1;

	/**
	 * Map of db schema version => array of migration method names (instance methods).
	 * A migration keyed to version N runs on any site whose stored db version is
	 * below N. Keys must be listed in ascending order.
	 *
	 * @var array<int, string[]>
	 */
	private const MIGRATIONS = array();

	private readonly TaskFieldRepository $task_field_repository;
	private readonly TaskFieldOptionRepository $task_field_option_repository;
	private readonly TaskStatusRoleService $task_status_role_service;

	/**
	 * Constructor.
	 *
	 * @param TaskFieldRepository       $task_field_repository        Default-field seeding.
	 * @param TaskFieldOptionRepository $task_field_option_repository Field-option seeding.
	 * @param TaskStatusRoleService     $task_status_role_service     Install-time role seeding.
	 */
	public function __construct(
		TaskFieldRepository $task_field_repository,
		TaskFieldOptionRepository $task_field_option_repository,
		TaskStatusRoleService $task_status_role_service
	) {
		$this->task_field_repository        = $task_field_repository;
		$this->task_field_option_repository = $task_field_option_repository;
		$this->task_status_role_service     = $task_status_role_service;
	}

	/**
	 * Run the installation process.
	 *
	 * @return void
	 */
	public function install(): void {
		if ( get_option( self::OPTION_VERSION ) ) {
			return;
		}

		self::create_tables();
		$this->insert_default_data();
		$this->seed_initial_state();

		update_option( self::OPTION_VERSION, OutTheDoor::VERSION, true );
		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, true );
	}

	/**
	 * Drop every plugin table and rebuild it from scratch (schema + default data).
	 *
	 * @return void
	 */
	public function reset(): void {
		self::drop_tables();

		// Clear install state so install() rebuilds instead of early-returning.
		foreach ( self::OPTION_NAMES as $option_name ) {
			delete_option( $option_name );
		}

		$this->install();
	}

	/**
	 * Check whether an upgrade is pending.
	 *
	 * @return bool
	 */
	public static function is_upgrade_due(): bool {
		if ( '' === (string) get_option( self::OPTION_VERSION ) ) {
			return true;
		}

		return (int) get_option( self::OPTION_DB_VERSION, 0 ) < self::DB_VERSION;
	}

	/**
	 * Run migrations.
	 *
	 * @return void
	 */
	public function upgrade(): void {
		// First install when there is no stored plugin version.
		if ( '' === (string) get_option( self::OPTION_VERSION ) ) {
			$this->install();

			return;
		}

		$current_db_version = (int) get_option( self::OPTION_DB_VERSION, 0 );

		// Nothing to do if the schema is current, or another request holds the lock.
		if ( $current_db_version >= self::DB_VERSION || ! $this->acquire_upgrade_lock() ) {
			return;
		}

		try {
			// Run every migration whose target schema version is newer than the site's.
			foreach ( self::MIGRATIONS as $version => $migration_callbacks ) {
				if ( $current_db_version < $version ) {
					foreach ( $migration_callbacks as $migration_method ) {
						$this->{$migration_method}();
					}
				}
			}

			// Record the new schema version and refresh the stored plugin version.
			update_option( self::OPTION_DB_VERSION, self::DB_VERSION, true );
			update_option( self::OPTION_VERSION, OutTheDoor::VERSION, true );
		} finally {
			$this->release_upgrade_lock();
		}
	}

	/**
	 * Create required database tables.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		$were_showing_errors = $wpdb->hide_errors();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( Schema::tables() );

		// dbDelta() does not handle foreign keys. The plugin works without them, so a key that
		// could not be added is left out.
		ForeignKeys::apply( Schema::foreign_keys() );

		if ( $were_showing_errors ) {
			$wpdb->show_errors();
		}
	}

	/**
	 * Drop all plugin tables, discovered by name prefix so new tables are
	 * included automatically. FK checks are disabled so drop order is irrelevant.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$like = $wpdb->esc_like( $wpdb->prefix . BaseRepository::TABLE_PREFIX ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Install-time table discovery.
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

		if ( empty( $tables ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Install-time schema teardown.
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Install-time DROP TABLE; $table is a trusted identifier from SHOW TABLES and table names cannot be bound as prepared-statement placeholders.
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Install-time schema teardown.
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
	}

	/**
	 * Insert default data into the database.
	 *
	 * @return void
	 */
	private function insert_default_data(): void {
		global $wpdb;

		// Each field keeps the same ID on every site, so code can name it. Users can edit an editable field, but never
		// delete a protected one. 'options' lists a select field's choices.
		$default_fields = array(
			// The task's column on the Kanban board.
			array(
				'id'           => DefaultTaskFields::STATUS,
				'label'        => 'Status',
				'type'         => 'select',
				'slug'         => 'status',
				'is_required'  => true,
				'is_editable'  => true,
				'is_protected' => true,
				'options'      => array(
					array(
						'label'    => 'Not started',
						'slug'     => 'not_started',
						'color'    => '#D5D7DA',
						'position' => 1,
					),
					array(
						'label'    => 'In progress',
						'slug'     => 'in_progress',
						'color'    => '#17a2b8',
						'position' => 2,
					),
					array(
						'label'    => 'Done',
						'slug'     => 'done',
						'color'    => '#28a745',
						'position' => 3,
					),
				),
			),
			// The task's order within its column.
			array(
				'id'           => DefaultTaskFields::POSITION,
				'label'        => 'Position',
				'type'         => 'number',
				'slug'         => 'position',
				'is_required'  => true,
				'is_editable'  => false,
				'is_protected' => true,
			),
			// The ID of the user who created the task.
			array(
				'id'           => DefaultTaskFields::CREATOR,
				'label'        => 'Creator',
				'type'         => 'number',
				'slug'         => 'creator',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			// The ID of the order the task belongs to.
			array(
				'id'           => DefaultTaskFields::ORDER,
				'label'        => 'Order',
				'type'         => 'number',
				'slug'         => 'order',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			array(
				'id'           => DefaultTaskFields::DUE_DATE,
				'label'        => 'Due Date',
				'type'         => 'date',
				'slug'         => 'due_date',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			// When the task reached the status assigned to the "done" role.
			array(
				'id'           => DefaultTaskFields::DONE_DATE,
				'label'        => 'Done Date',
				'type'         => 'date',
				'slug'         => 'done_date',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			// When the task was archived. Archiving keeps the task's status.
			array(
				'id'           => DefaultTaskFields::ARCHIVED_DATE,
				'label'        => 'Archived Date',
				'type'         => 'date',
				'slug'         => 'archived_date',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			array(
				'id'           => DefaultTaskFields::PRIORITY,
				'label'        => 'Priority',
				'type'         => 'select',
				'slug'         => 'priority',
				'is_required'  => false,
				'is_editable'  => true,
				'is_protected' => false,
				'options'      => array(
					array(
						'label'    => 'Low',
						'slug'     => 'low',
						'color'    => '#D1E9FF',
						'position' => 1,
					),
					array(
						'label'    => 'Medium',
						'slug'     => 'medium',
						'color'    => '#FFF2CC',
						'position' => 2,
					),
					array(
						'label'    => 'High',
						'slug'     => 'high',
						'color'    => '#FFF3E0',
						'position' => 3,
					),
					array(
						'label'    => 'Critical',
						'slug'     => 'critical',
						'color'    => '#FFEBEE',
						'position' => 4,
					)
				),
			),
		);

		$table_name = $this->task_field_repository->get_table_full_name();

		foreach ( $default_fields as $field_data ) {
			$field_id = $field_data['id'];

			if ( $this->task_field_repository->find( $field_id ) ) {
				continue;
			}

			$field_options = $field_data['options'] ?? array();
			unset( $field_data['options'] );

			// A raw INSERT, so the field gets exactly its ID.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Install-time seeding; preserves the exact field ID.
			$result = $wpdb->insert( $table_name, $field_data );

			if ( $result ) {
				$inserted_id = (int) $wpdb->insert_id;

				if ( $inserted_id !== $field_id ) {
					Logger::critical(
						sprintf(
							'Failed to insert field with ID %d. Got ID %d instead.',
							$field_id,
							$inserted_id
						)
					);
					continue;
				}

				foreach ( $field_options as $option ) {
					$this->task_field_option_repository
						->insert( array_merge( $option, array( 'field_id' => $inserted_id ) ) );
				}
			} else {
				Logger::critical( sprintf( 'Failed to insert default field "%s". DB error: %s', $field_data['slug'], $wpdb->last_error ) );
			}
		}

		// The next field created gets the ID after the highest one.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Install-time check; cache would be stale immediately after seeding. The table name is the repository's own.
		$max_id = $wpdb->get_var( "SELECT MAX(id) FROM {$table_name}" );
		if ( $max_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Install-time AUTO_INCREMENT reset; schema change is intentional. The table name is the repository's own.
			$wpdb->query( $wpdb->prepare( "ALTER TABLE {$table_name} AUTO_INCREMENT = %d", $max_id + 1 ) );
		}
	}

	/**
	 * Trigger first-install seeding for services that need DB-derived initial state.
	 *
	 * @return void
	 */
	private function seed_initial_state(): void {
		$this->task_status_role_service->seed_default_role_assignments();
	}

	/**
	 * Take the upgrade lock for 2 minutes, so concurrent requests do not run the migrations twice.
	 *
	 * @return bool False when another request holds it.
	 */
	private function acquire_upgrade_lock(): bool {
		if ( (int) get_option( self::OPTION_UPGRADE_LOCK, 0 ) > time() ) {
			return false;
		}

		update_option( self::OPTION_UPGRADE_LOCK, time() + 120, false );

		return true;
	}

	/**
	 * Release the upgrade lock.
	 *
	 * @return void
	 */
	private function release_upgrade_lock(): void {
		delete_option( self::OPTION_UPGRADE_LOCK );
	}
}
