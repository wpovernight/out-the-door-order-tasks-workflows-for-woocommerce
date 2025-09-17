<?php

namespace WPO\AOM\Core;

use WPO\AOM\AdvancedOrderManager;
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

		if ( version_compare( $current_version, AdvancedOrderManager::VERSION, '>=' ) ) {
			return; // No upgrade needed.
		}

		if ( ! self::acquire_upgrade_lock() ) {
			return; // Another process is already running migrations.
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
	private static function create_tables(): void {
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
			is_required TINYINT(1) NOT NULL DEFAULT 0,
			is_editable TINYINT(1) NOT NULL DEFAULT 1,
			is_protected TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) {$charset_collate};
		CREATE TABLE `{$wpdb->prefix}wpo_aom_task_field_options` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			field_id BIGINT(20) UNSIGNED NOT NULL,
			label VARCHAR(255) NOT NULL,
			color VARCHAR(7) DEFAULT NULL,
			PRIMARY KEY  (id),
			FOREIGN KEY (field_id) REFERENCES {$wpdb->prefix}wpo_aom_task_fields(id) ON DELETE CASCADE
		) {$charset_collate};
		CREATE TABLE `{$wpdb->prefix}wpo_aom_task_field_values` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			task_id BIGINT(20) UNSIGNED NOT NULL,
			field_id BIGINT(20) UNSIGNED NOT NULL,
			value TEXT DEFAULT NULL,
			PRIMARY KEY  (id),
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
		$default_fields = array(
			array(
				'label'        => 'Assignee',
				'type'         => 'user',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
			array(
				'label'        => 'Order ID',
				'type'         => 'order',
				'is_required'  => false,
				'is_editable'  => false,
				'is_protected' => true,
			),
		);

		$task_field_repository = new TaskFieldRepository();
		$all_fields            = $task_field_repository->get();

		foreach ( $default_fields as $field_data ) {
			$exists = false;
			foreach ( $all_fields as $existing_field ) {
				if (
					$existing_field->label === $field_data['label'] &&
					$existing_field->type === $field_data['type']
				) {
					$exists = true;
					break;
				}
			}

			if ( ! $exists ) {
				$task_field_repository->insert( $field_data );
			}
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
