<?php

namespace WPO\OTD\Tests\Integration\Core;

use WPO\OTD\Core\Installer;
use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Enums\TaskStatusRoleSettingKeys;
use WPO\OTD\OutTheDoor;
use WPO\OTD\Services\TaskManagerSettingsService;
use WPO\OTD\Tests\IntegrationTestCase;

/**
 * The installer as the activation hook left it, and its maintenance paths.
 *
 * Schema changes commit the test's transaction, so they cannot be rolled back. Tests that
 * change the schema or the install state flag it, and tear_down() reinstalls from scratch.
 */
class InstallerTest extends IntegrationTestCase {
	private const TABLES = array( 'tasks', 'task_fields', 'task_field_options', 'task_field_values', 'custom_statuses' );

	/**
	 * Whether this test committed changes that tear_down() must undo by reinstalling.
	 *
	 * @var bool
	 */
	private bool $needs_reinstall = false;

	public function tear_down() {
		parent::tear_down();

		if ( $this->needs_reinstall ) {
			global $wpdb;

			// The rollback left the options cache describing the test's state, e.g. a version
			// option that no longer exists, which would make install() return early.
			self::flush_cache();

			// Outside the test's transaction now, so this rebuilds the real tables.
			( new \WPO\OTD\Core\Container\Container() )->get( Installer::class )->reset();
			$wpdb->query( 'COMMIT' );
			self::clear_repository_caches();
		}
	}

	/** ================================
	 *   State after activation
	 *  ================================ */

	public function test_activation_created_every_table_as_innodb(): void {
		foreach ( self::TABLES as $table ) {
			$this->assertSame( 'InnoDB', $this->engine( $this->table( $table ) ), "{$table} is missing or not InnoDB." );
		}
	}

	public function test_activation_created_the_foreign_keys_with_cascading_deletes(): void {
		$this->assertSame(
			array(
				'task_field_options.field_id -> task_fields.id CASCADE',
				'task_field_values.field_id -> task_fields.id CASCADE',
				'task_field_values.task_id -> tasks.id CASCADE',
			),
			$this->foreign_keys()
		);
	}

	public function test_the_seeded_fields_match_the_default_task_fields_enum(): void {
		global $wpdb;

		$rows = $wpdb->get_results( "SELECT * FROM {$this->table( 'task_fields' )} ORDER BY id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertSame(
			array(
				DefaultTaskFields::STATUS        => 'status:select',
				DefaultTaskFields::POSITION      => 'position:number',
				DefaultTaskFields::CREATOR       => 'creator:number',
				DefaultTaskFields::ORDER         => 'order:number',
				DefaultTaskFields::DUE_DATE      => 'due_date:date',
				DefaultTaskFields::DONE_DATE     => 'done_date:date',
				DefaultTaskFields::ARCHIVED_DATE => 'archived_date:date',
				DefaultTaskFields::PRIORITY      => 'priority:select',
			),
			array_combine(
				array_map( 'intval', array_column( $rows, 'id' ) ),
				array_map( static fn( $row ) => $row['slug'] . ':' . $row['type'], $rows )
			)
		);

		foreach ( $rows as $row ) {
			$id = (int) $row['id'];

			$this->assertSame( DefaultTaskFields::is_protected( $id ), (bool) $row['is_protected'], "is_protected of field {$id}" );
			$this->assertSame( DefaultTaskFields::is_required( $id ), (bool) $row['is_required'], "is_required of field {$id}" );
			$this->assertSame( DefaultTaskFields::is_editable( $id ), (bool) $row['is_editable'], "is_editable of field {$id}" );
		}
	}

	public function test_the_status_and_priority_options_are_seeded_in_order(): void {
		$this->assertSame( array( 'not_started', 'in_progress', 'done' ), $this->option_slugs( DefaultTaskFields::STATUS ) );
		$this->assertSame( array( 'low', 'medium', 'high', 'critical' ), $this->option_slugs( DefaultTaskFields::PRIORITY ) );
	}

	public function test_the_done_and_undone_roles_are_seeded(): void {
		$settings = get_option( TaskManagerSettingsService::OPTION_NAME );

		$this->assertSame( $this->status_option_id( 'done' ), $settings[ TaskStatusRoleSettingKeys::DONE ] );
		$this->assertSame( $this->status_option_id( 'in_progress' ), $settings[ TaskStatusRoleSettingKeys::UNDONE ] );
	}

	public function test_the_installed_versions_are_recorded(): void {
		$this->assertSame( OutTheDoor::VERSION, get_option( 'wpo_otd_version' ) );
		$this->assertSame( 1, (int) get_option( 'wpo_otd_db_version' ) );
		$this->assertFalse( Installer::is_upgrade_due() );
	}

	public function test_custom_fields_never_reuse_a_seeded_id(): void {
		global $wpdb;

		$wpdb->insert( $this->table( 'task_fields' ), array( 'label' => 'Custom', 'type' => 'text', 'slug' => 'custom' ) );

		// Rolled-back inserts of other tests also consume ids, so only the lower bound is fixed.
		$this->assertGreaterThan( max( DefaultTaskFields::all() ), (int) $wpdb->insert_id );
	}

	/** ================================
	 *   install() / reset() / drop_tables()
	 *  ================================ */

	public function test_install_does_nothing_once_installed(): void {
		global $wpdb;

		$wpdb->delete( $this->table( 'task_field_options' ), array( 'slug' => 'critical' ) );

		$this->make( Installer::class )->install();

		$this->assertNotContains( 'critical', $this->option_slugs( DefaultTaskFields::PRIORITY ) );
	}

	public function test_reset_rebuilds_the_tables_and_the_defaults(): void {
		global $wpdb;

		$this->needs_reinstall = true;
		$this->allow_real_ddl();

		$wpdb->insert( $this->table( 'tasks' ), array( 'title' => 'Left over' ) );
		$wpdb->delete( $this->table( 'task_field_options' ), array( 'slug' => 'critical' ) );
		$wpdb->insert( $this->table( 'custom_statuses' ), array( 'status_key' => 'left-over', 'label' => 'Left over' ) );

		$this->make( Installer::class )->reset();

		$this->assertSame( 0, $this->count_rows( 'tasks' ) );
		$this->assertSame( 0, $this->count_rows( 'custom_statuses' ) );
		$this->assertSame( 8, $this->count_rows( 'task_fields' ) );
		$this->assertContains( 'critical', $this->option_slugs( DefaultTaskFields::PRIORITY ) );
		$this->assertSame( OutTheDoor::VERSION, get_option( 'wpo_otd_version' ) );
		$this->assertCount( 3, $this->foreign_keys() );
	}

	public function test_drop_tables_removes_every_plugin_table(): void {
		global $wpdb;

		$this->needs_reinstall = true;
		$this->allow_real_ddl();

		Installer::drop_tables();

		$this->assertSame( array(), $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'wpo_otd_' ) . '%' ) ) );

		// Nothing left to drop is not an error.
		Installer::drop_tables();
	}

	public function test_create_tables_restores_missing_foreign_keys_after_removing_orphans(): void {
		global $wpdb;

		$this->needs_reinstall = true;
		$this->allow_real_ddl();

		$values = $this->table( 'task_field_values' );
		$wpdb->query( "ALTER TABLE `{$values}` DROP FOREIGN KEY `{$values}_task_id_fk`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Without the constraint a value can point at a task that does not exist.
		$wpdb->insert( $values, array( 'task_id' => 999, 'field_id' => DefaultTaskFields::STATUS, 'value' => '1' ) );

		Installer::create_tables();

		$this->assertSame( 0, $this->count_rows( 'task_field_values' ), 'The orphaned value must be removed.' );
		$this->assertContains( 'task_field_values.task_id -> tasks.id CASCADE', $this->foreign_keys() );
	}

	public function test_create_tables_converts_a_non_innodb_table_before_adding_its_keys(): void {
		global $wpdb;

		$this->needs_reinstall = true;
		$this->allow_real_ddl();

		$values = $this->table( 'task_field_values' );
		$wpdb->query( "ALTER TABLE `{$values}` DROP FOREIGN KEY `{$values}_task_id_fk`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE `{$values}` DROP FOREIGN KEY `{$values}_field_id_fk`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE `{$values}` ENGINE=MyISAM" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		Installer::create_tables();

		$this->assertSame( 'InnoDB', $this->engine( $values ) );
		$this->assertCount( 3, $this->foreign_keys() );
	}

	/** ================================
	 *   Upgrades
	 *  ================================ */

	public function test_an_upgrade_is_due_without_an_install_or_with_an_older_schema(): void {
		update_option( 'wpo_otd_db_version', 0 );
		$this->assertTrue( Installer::is_upgrade_due() );

		update_option( 'wpo_otd_db_version', 1 );
		delete_option( 'wpo_otd_version' );
		$this->assertTrue( Installer::is_upgrade_due() );
	}

	public function test_upgrade_records_the_new_versions_and_releases_its_lock(): void {
		update_option( 'wpo_otd_db_version', 0 );
		update_option( 'wpo_otd_version', '0.9.0' );

		$this->make( Installer::class )->upgrade();

		$this->assertSame( 1, (int) get_option( 'wpo_otd_db_version' ) );
		$this->assertSame( OutTheDoor::VERSION, get_option( 'wpo_otd_version' ) );
		$this->assertFalse( get_option( 'wpo_otd_upgrade_lock' ) );
	}

	public function test_upgrade_waits_while_another_request_holds_the_lock(): void {
		update_option( 'wpo_otd_db_version', 0 );
		update_option( 'wpo_otd_upgrade_lock', time() + 60 );

		$this->make( Installer::class )->upgrade();

		$this->assertSame( 0, (int) get_option( 'wpo_otd_db_version' ) );
	}

	public function test_upgrade_takes_over_an_expired_lock(): void {
		update_option( 'wpo_otd_db_version', 0 );
		update_option( 'wpo_otd_upgrade_lock', time() - 1 );

		$this->make( Installer::class )->upgrade();

		$this->assertSame( 1, (int) get_option( 'wpo_otd_db_version' ) );
	}

	public function test_upgrade_does_nothing_when_the_schema_is_current(): void {
		update_option( 'wpo_otd_version', '0.9.0' );

		$this->make( Installer::class )->upgrade();

		$this->assertSame( '0.9.0', get_option( 'wpo_otd_version' ) );
	}

	public function test_upgrade_installs_a_site_that_was_never_installed(): void {
		// Installing resets the field table's AUTO_INCREMENT, which commits.
		$this->needs_reinstall = true;
		$this->allow_real_ddl();

		delete_option( 'wpo_otd_version' );
		delete_option( 'wpo_otd_db_version' );

		$this->make( Installer::class )->upgrade();

		$this->assertSame( OutTheDoor::VERSION, get_option( 'wpo_otd_version' ) );
		$this->assertSame( 1, (int) get_option( 'wpo_otd_db_version' ) );
	}

	/**
	 * Get a table's storage engine, or null when it does not exist.
	 *
	 * @param string $table Full table name.
	 *
	 * @return string|null
	 */
	private function engine( string $table ): ?string {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table )
		);
	}

	/**
	 * Describe the plugin's foreign keys as "child.column -> parent.column RULE", sorted.
	 *
	 * @return string[]
	 */
	private function foreign_keys(): array {
		global $wpdb;

		$prefix = $wpdb->prefix . 'wpo_otd_';
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
				FROM information_schema.KEY_COLUMN_USAGE k
				JOIN information_schema.REFERENTIAL_CONSTRAINTS r
					ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
				WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME LIKE %s',
				$wpdb->esc_like( $prefix ) . '%'
			),
			ARRAY_A
		);

		$keys = array_map(
			static fn( $row ) => sprintf(
				'%s.%s -> %s.%s %s',
				substr( $row['TABLE_NAME'], strlen( $prefix ) ),
				$row['COLUMN_NAME'],
				substr( $row['REFERENCED_TABLE_NAME'], strlen( $prefix ) ),
				$row['REFERENCED_COLUMN_NAME'],
				$row['DELETE_RULE']
			),
			$rows
		);

		sort( $keys );

		return $keys;
	}

	/**
	 * Get a field's option slugs in position order.
	 *
	 * @param int $field_id
	 *
	 * @return string[]
	 */
	private function option_slugs( int $field_id ): array {
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare( "SELECT slug FROM {$this->table( 'task_field_options' )} WHERE field_id = %d ORDER BY position", $field_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
