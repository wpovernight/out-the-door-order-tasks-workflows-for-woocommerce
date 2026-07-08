<?php

namespace WPO\AOM\Core;

use WPO\AOM\AdvancedOrderManager;
use WPO\AOM\Repositories\TaskFieldOptionRepository;
use WPO\AOM\Repositories\TaskFieldRepository;
use WPO\AOM\Services\TaskStatusRoleService;

defined( 'ABSPATH' ) || exit;

final class Installer {
	private const OPTION_VERSION      = 'wpo_aom_version';
	private const OPTION_DB_VERSION   = 'wpo_aom_db_version';
	private const OPTION_UPGRADE_LOCK = 'wpo_aom_upgrade_lock';
	private const TABLE_PREFIX        = 'wpo_aom_';

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
	private const MIGRATIONS = array(
		1 => array(
			'migrate_apply_option_slug_unique_index', /** @uses migrate_apply_option_slug_unique_index() */
			'migrate_seed_status_role_assignments', /** @uses migrate_seed_status_role_assignments() */
		),
	);

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
			// Already installed.
			return;
		}

		self::create_tables();
		$this->insert_default_data();
		$this->seed_initial_state();

		// Record the installed plugin version and the current schema version.
		update_option( self::OPTION_VERSION, AdvancedOrderManager::VERSION, true );
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
						if ( is_callable( array( $this, $migration_method ) ) ) {
							$this->{$migration_method}();
						}
					}
				}
			}

			// Record the new schema version and refresh the stored plugin version.
			update_option( self::OPTION_DB_VERSION, self::DB_VERSION, true );
			update_option( self::OPTION_VERSION, AdvancedOrderManager::VERSION, true );
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

		dbDelta( self::get_schema() );

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

		$like = $wpdb->esc_like( $wpdb->prefix . self::TABLE_PREFIX ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Install-time table discovery.
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

		if ( empty( $tables ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Install-time schema teardown.
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Install-time DROP TABLE; $table is a trusted name from SHOW TABLES.
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Install-time schema teardown.
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
	}

	/**
	 * Get tables schema for dbDelta().
	 *
	 * @return string
	 */
	private static function get_schema(): string {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		return "
		CREATE TABLE `{$wpdb->prefix}wpo_aom_tasks` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			description TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) {$charset_collate};
		CREATE TABLE `{$wpdb->prefix}wpo_aom_task_fields` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			label VARCHAR(255) NOT NULL,
			type VARCHAR(20) NOT NULL,
			slug VARCHAR(255) NOT NULL,
			is_required TINYINT(1) NOT NULL DEFAULT 0,
			is_editable TINYINT(1) NOT NULL DEFAULT 1,
			is_protected TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) {$charset_collate};
		CREATE TABLE `{$wpdb->prefix}wpo_aom_task_field_options` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			field_id BIGINT(20) UNSIGNED NOT NULL,
			slug VARCHAR(255) NOT NULL,
			label VARCHAR(255) NOT NULL,
			color VARCHAR(7) DEFAULT NULL,
			position INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY field_slug_unique (field_id, slug),
			FOREIGN KEY (field_id) REFERENCES {$wpdb->prefix}wpo_aom_task_fields(id) ON DELETE CASCADE
		) {$charset_collate};
		CREATE TABLE `{$wpdb->prefix}wpo_aom_task_field_values` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			task_id BIGINT(20) UNSIGNED NOT NULL,
			field_id BIGINT(20) UNSIGNED NOT NULL,
			value TEXT DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_task_field_lookup (task_id, field_id),
			FOREIGN KEY (task_id) REFERENCES {$wpdb->prefix}wpo_aom_tasks(id) ON DELETE CASCADE,
			FOREIGN KEY (field_id) REFERENCES {$wpdb->prefix}wpo_aom_task_fields(id) ON DELETE CASCADE
		) {$charset_collate};
		CREATE TABLE `{$wpdb->prefix}wpo_aom_custom_statuses` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			status_key VARCHAR(64) NOT NULL,
			label VARCHAR(255) NOT NULL,
			background VARCHAR(32) DEFAULT NULL,
			is_deleting TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY (status_key)
		) {$charset_collate};
		";
	}

	/**
	 * Insert default data into the database.
	 *
	 * @return void
	 */
	private function insert_default_data(): void {
		global $wpdb;

		/**
		 * Note: When adding new default fields, ensure that the ID is unique and does
		 * not conflict with existing fields. Also, update the DefaultTaskFields enum
		 * class accordingly to maintain a single source of truth for default field IDs.
		 *
		 * Default fields to be inserted on plugin activation.
		 * The IDs are hardcoded to ensure consistency across installations and to allow
		 * referencing in code. Protected fields (is_protected = true) cannot be deleted
		 * by users and are essential for the plugin's core functionality. Editable fields
		 * (is_editable = true) can be modified by users, but protected fields cannot be
		 * deleted to ensure the integrity. The 'options' key is only applicable for 'select'
		 * type fields and defines the available options for that field.
		 */
		$default_fields = array(
			/*
			 * Default fields: Non-editable and protected fields.
			 */
			// Status(column in Kanban view) field.
			array(
				'id'           => 1,
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
			// Position within status - used for ordering tasks within a status column in Kanban view
			array(
				'id'           => 2,
				'label'        => 'Position',
				'type'         => 'number',
				'slug'         => 'position',
				'is_required'  => true,
				'is_editable'  => false,
				'is_protected' => true,
			),
			// User ID of the creator - used to store which user created the task.
			array(
				'id'           => 3,
				'label'        => 'Creator',
				'type'         => 'number',
				'slug'         => 'creator',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			// Order ID - used to associate the task with a specific order.
			array(
				'id'           => 4,
				'label'        => 'Order',
				'type'         => 'number',
				'slug'         => 'order',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			// Due date - used to store the deadline for the task.
			array(
				'id'           => 5,
				'label'        => 'Due Date',
				'type'         => 'date',
				'slug'         => 'due_date',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			// Done date - used to store the date when a task is marked as done (status changed to "Done")
			array(
				'id'           => 6,
				'label'        => 'Done Date',
				'type'         => 'date',
				'slug'         => 'done_date',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			// Archived date - used to store the date when a task is archived (status changed to "Archived")
			array(
				'id'           => 7,
				'label'        => 'Archived Date',
				'type'         => 'date',
				'slug'         => 'archived_date',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			/*
			 * Editable and non-protected fields.
			 */
			array(
				'id'           => 8,
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

		$task_field_repository        = $this->task_field_repository;
		$task_field_option_repository = $this->task_field_option_repository;

		foreach ( $default_fields as $field_data ) {
			$field_id = $field_data['id'];

			// Check if field with this specific ID already exists.
			$existing_field = $task_field_repository->find( $field_id );
			if ( $existing_field ) {
				continue;
			}

			$field_options = $field_data['options'] ?? array();
			unset( $field_data['options'] );

			// Use raw INSERT to ensure the exact ID is used.
			$table_name = $wpdb->prefix . 'wpo_aom_task_fields';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Install-time seeding; preserves the exact field ID.
			$result     = $wpdb->insert( $table_name, $field_data );

			if ( $result ) {
				$inserted_id = (int) $wpdb->insert_id;

				// Verify the ID matches what we expected.
				if ( $inserted_id !== $field_id ) {
					// Log error or throw exception - ID mismatch is critical.
					Logger::critical(
						sprintf(
							'Failed to insert field with ID %d. Got ID %d instead.',
							$field_id,
							$inserted_id
						)
					);
					continue;
				}

				// Insert options if it's a select field.
				if ( ! empty( $field_options ) ) {
					foreach ( $field_options as $option ) {
						$task_field_option_repository
							->insert( array_merge( $option, array( 'field_id' => $inserted_id ) ) );
					}
				}
			} else {
				Logger::critical( sprintf( 'Failed to insert default field "%s". DB error: %s', $field_data['slug'], $wpdb->last_error ) );
			}
		}

		// Reset auto-increment to prevent gaps if needed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Install-time check; cache would be stale immediately after seeding.
		$max_id = $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}wpo_aom_task_fields" );
		if ( $max_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Install-time AUTO_INCREMENT reset; schema change is intentional.
			$wpdb->query( $wpdb->prepare( "ALTER TABLE {$wpdb->prefix}wpo_aom_task_fields AUTO_INCREMENT = %d", $max_id + 1 ) );
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
	 * Re-run create_tables() so dbDelta applies pending schema changes for the
	 * beta.2 upgrade. Two changes ride along on this single dbDelta pass:
	 *  - the new UNIQUE KEY on wpo_aom_task_field_options(field_id, slug), and
	 *  - the new is_deleting column on wpo_aom_custom_statuses.
	 *
	 * Existing beta.1 installs only contain the seeded options (no public
	 * create-option path existed), so seed data is already unique and no dedupe
	 * is needed.
	 *
	 * @return void
	 */
	private function migrate_apply_option_slug_unique_index(): void {
		self::create_tables();
	}

	/**
	 * Seed the default "done" / "undone" status role assignments for installs
	 * that predate the role feature.
	 *
	 * @return void
	 */
	private function migrate_seed_status_role_assignments(): void {
		$this->task_status_role_service->seed_default_role_assignments();
	}

	/**
	 * Acquire a lock for the upgrade process.
	 *
	 * This prevents concurrent requests from running migrations multiple times.
	 *
	 * @return bool
	 */
	private function acquire_upgrade_lock(): bool {
		// Try to acquire a short-lived lock (prevents concurrent requests running migrations twice).
		$locked_until = (int) get_option( self::OPTION_UPGRADE_LOCK, 0 );

		if ( $locked_until > time() ) {
			return false; // Another process is migrating.
		}

		// Lock the upgrade process for 2 minutes to prevent concurrent migrations.
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
