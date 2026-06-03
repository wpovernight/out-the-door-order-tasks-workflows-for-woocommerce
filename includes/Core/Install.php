<?php

namespace WPO\AOM\Core;

use WPO\AOM\AdvancedOrderManager;
use WPO\AOM\Repositories\TaskFieldOptionRepository;
use WPO\AOM\Repositories\TaskFieldRepository;
use WPO\AOM\Services\TaskStatusRoleService;

defined( 'ABSPATH' ) || exit;

final class Install {
	private static string $option_version      = 'wpo_aom_version';
	private static string $option_upgrade_lock = 'wpo_aom_upgrade_lock';

	/**
	 * Map of version => array of migration method names (static within this class).
	 * Notice: The version sequence should be in ascending order.
	 *
	 * Example:
	 * '1.1.0' => array( 'migrate_add_custom_statuses_feature' )
	 *
	 * @var array<string, string[]>
	 */
	private static array $migrations = array(
		'1.0.0-beta.2' => array(
			'migrate_apply_option_slug_unique_index', /** @uses migrate_apply_option_slug_unique_index() */
			'migrate_seed_status_role_assignments', /** @uses migrate_seed_status_role_assignments() */
		),
	);

	protected static ?self $_instance = null;

	/**
	 * Get the instance of the class.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Register the installation and upgrade hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Create tables & set version immediately on activation.
		register_activation_hook( WPO_AOM_PLUGIN_FILE, array( self::class, 'install' ) );

		// Run upgrade when an admin page is loaded.
		add_action( 'admin_init', array( self::class, 'upgrade' ) );
	}

	/**
	 * Run the installation process.
	 *
	 * @return void
	 */
	public static function install(): void {
		if ( get_option( self::$option_version ) ) {
			// Already installed.
			return;
		}

		self::create_tables();
		self::insert_default_data();
		self::seed_initial_state();

		// Store the plugin version in the options table.
		update_option( self::$option_version, AdvancedOrderManager::VERSION, true );
	}

	/**
	 * Run migrations.
	 *
	 * @return void
	 */
	public static function upgrade(): void {
		$current_version = (string) get_option( self::$option_version );

		// If the version is not set, it means this is a fresh installation.
		if ( empty( $current_version ) ) {
			self::install();

			return;
		}

		// If the current version is the same or higher, or if we can't acquire the lock, do nothing.
		if (
			version_compare( $current_version, AdvancedOrderManager::VERSION, '>=' ) ||
			! self::acquire_upgrade_lock()
		) {
			return;
		}

		try {
			// Loop through migrations and run them if the version matches.
			foreach ( self::$migrations as $version => $migration_callbacks ) {
				if ( version_compare( $current_version, $version, '<' ) ) {
					foreach ( $migration_callbacks as $migration_method ) {
						if ( is_callable( array( self::class, $migration_method ) ) ) {
							call_user_func( array( self::class, $migration_method ) );
						}
					}
				}
			}

			// Store the plugin version in the options table.
			update_option( self::$option_version, AdvancedOrderManager::VERSION, true );
		} finally {
			self::release_upgrade_lock();
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
			PRIMARY KEY (id),
			UNIQUE KEY (status_key)
		) {$charset_collate};
		";
	}

	/**
	 * Insert default data into the database.
	 *
	 * @return void
	 */
	private static function insert_default_data(): void {
		global $wpdb;

		/**
		 * Note: When adding new default fields, ensure that the ID is unique and does not conflict with existing fields.
		 *       Also, update the DefaultTaskFields enum class accordingly to maintain a single source of truth for default field IDs.
		 *
		 * Default fields to be inserted on plugin activation.
		 * The IDs are hardcoded to ensure consistency across installations and to allow referencing in code.
		 * Protected fields (is_protected = true) cannot be deleted by users and are essential for the plugin's core functionality.
		 * Editable fields (is_editable = true) can be modified by users, but protected fields cannot be deleted to ensure the integrity.
		 * The 'options' key is only applicable for 'select' type fields and defines the available options for that field.
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

		$task_field_repository        = new TaskFieldRepository();
		$task_field_option_repository = new TaskFieldOptionRepository();

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
	private static function seed_initial_state(): void {
		/** @var TaskStatusRoleService $task_status_role_service */
		$task_status_role_service = WPO_AOM()->get_service( TaskStatusRoleService::class );
		$task_status_role_service->seed_default_role_assignments();
	}

	/**
	 * Re-run create_tables() so dbDelta applies the new UNIQUE KEY on
	 * wpo_aom_task_field_options(field_id, slug). Existing beta.1 installs only
	 * contain the seeded options (no public create-option path existed), so
	 * seed data is already unique and no dedupe is needed.
	 *
	 * @return void
	 */
	private static function migrate_apply_option_slug_unique_index(): void {
		self::create_tables();
	}

	/**
	 * Seed the default "done" / "undone" status role assignments for installs
	 * that predate the role feature.
	 *
	 * @return void
	 */
	private static function migrate_seed_status_role_assignments(): void {
		/** @var TaskStatusRoleService $task_status_role_service */
		$task_status_role_service = WPO_AOM()->get_service( TaskStatusRoleService::class );
		$task_status_role_service->seed_default_role_assignments();
	}

	/**
	 * Acquire a lock for the upgrade process.
	 *
	 * This prevents concurrent requests from running migrations multiple times.
	 *
	 * @return bool
	 */
	private static function acquire_upgrade_lock(): bool {
		// Try to acquire a short-lived lock (prevents concurrent requests running migrations twice).
		$locked_until = (int) get_option( self::$option_upgrade_lock, 0 );

		if ( $locked_until > time() ) {
			return false; // Another process is migrating.
		}

		// Lock the upgrade process for 2 minutes to prevent concurrent migrations.
		update_option( self::$option_upgrade_lock, time() + 120, false );

		return true;
	}

	/**
	 * Release the upgrade lock.
	 *
	 * @return void
	 */
	private static function release_upgrade_lock(): void {
		delete_option( self::$option_upgrade_lock );
	}
}
