<?php

namespace WPO\AOM\Core;

use WPO\AOM\AdvancedOrderManager;
use WPO\AOM\Repositories\TaskFieldOptionRepository;
use WPO\AOM\Repositories\TaskFieldRepository;

defined( 'ABSPATH' ) || exit;

final class Install {
	private static string $option_version      = 'wpo_aom_version';
	private static string $option_upgrade_lock = 'wpo_aom_upgrade_lock';

	/**
	 * Map of version => array of migration method names (static within this class).
	 * Notice: The version sequence should be in ascending order.
	 *
	 * Example:
	 * '1.1.0' => array( 'migrate_110_add_custom_statuses_feature' )
	 *
	 * @var array<string, string[]>
	 */
	private static array $migrations = array();

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
	 * @return void
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
		";
	}

	/**
	 * Insert default data into the database.
	 *
	 * @return void
	 */
	private static function insert_default_data(): void {
		global $wpdb;

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
						'label'    => 'To Do',
						'slug'     => 'to_do',
						'color'    => '#6c757d',
						'position' => 1,
					),
					array(
						'label'    => 'In Progress',
						'slug'     => 'in_progress',
						'color'    => '#17a2b8',
						'position' => 2,
					),
					array(
						'label'    => 'Completed',
						'slug'     => 'completed',
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
			array(
				'id'           => 3,
				'label'        => 'Creator',
				'type'         => 'number',
				'slug'         => 'creator',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			array(
				'id'           => 4,
				'label'        => 'Order',
				'type'         => 'number',
				'slug'         => 'order',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			array(
				'id'           => 5,
				'label'        => 'Due Date',
				'type'         => 'date',
				'slug'         => 'due_date',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			/*
			 * Editable and non-protected fields.
			 */
			array(
				'id'           => 6,
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
						'color'    => '#34c38f',
						'position' => 1,
					),
					array(
						'label'    => 'Medium',
						'slug'     => 'medium',
						'color'    => '#f1b44c',
						'position' => 2,
					),
					array(
						'label'    => 'High',
						'slug'     => 'high',
						'color'    => '#f46a6a',
						'position' => 3,
					),
					array(
						'label'    => 'Critical',
						'slug'     => 'critical',
						'color'    => '#f46a6a',
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

			if ( ! $existing_field ) {
				$field_options = $field_data['options'] ?? array();
				unset( $field_data['options'] );

				// Use raw INSERT to ensure the exact ID is used.
				$table_name = $wpdb->prefix . 'wpo_aom_task_fields';
				$result     = $wpdb->insert( $table_name, $field_data );

				if ( $result ) {
					$inserted_id = (int) $wpdb->insert_id;

					// Verify the ID matches what we expected.
					if ( $inserted_id !== $field_id ) {
						// Log error or throw exception - ID mismatch is critical.
						error_log(
							sprintf(
								'WPO AOM: Failed to insert field with ID %d. Got ID %d instead.',
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
				}
			}
		}

		// Reset auto-increment to prevent gaps if needed.
		$max_id = $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}wpo_aom_task_fields" );
		if ( $max_id ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE {$wpdb->prefix}wpo_aom_task_fields AUTO_INCREMENT = %d", $max_id + 1 ) );
		}
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

		if ( $locked_until > current_time( 'timestamp' ) ) {
			return false; // Another process is migrating.
		}

		// Lock the upgrade process for 2 minutes to prevent concurrent migrations.
		update_option( self::$option_upgrade_lock, current_time( 'timestamp' ) + 120, false );

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
