<?php

namespace WPO\OTD\Tests\Unit\Core;

use Mockery;
use WPO\OTD\Core\Schema;
use WPO\OTD\Tests\TestCase;

/**
 * The CREATE TABLE statements and the foreign keys that must line up with them.
 */
class SchemaTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'get_charset_collate' )->andReturn( 'DEFAULT CHARSET=utf8mb4' );

		$GLOBALS['wpdb'] = $wpdb;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );

		parent::tearDown();
	}

	public function test_tables_are_prefixed_innodb_with_the_site_charset(): void {
		$columns = $this->columns_by_table();

		$this->assertSame(
			array( 'wp_wpo_otd_tasks', 'wp_wpo_otd_task_fields', 'wp_wpo_otd_task_field_options', 'wp_wpo_otd_task_field_values', 'wp_wpo_otd_custom_statuses' ),
			array_keys( $columns )
		);
		$this->assertSame( count( $columns ), substr_count( Schema::tables(), ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;' ) );
	}

	public function test_tables_leave_foreign_keys_out_for_dbdelta(): void {
		$this->assertStringNotContainsStringIgnoringCase( 'FOREIGN KEY', Schema::tables() );
		$this->assertStringNotContainsStringIgnoringCase( 'REFERENCES', Schema::tables() );
	}

	public function test_every_foreign_key_names_columns_the_tables_define(): void {
		$columns = $this->columns_by_table();

		foreach ( Schema::foreign_keys() as $foreign_key ) {
			$table  = 'wp_' . $foreign_key['table'];
			$parent = 'wp_' . $foreign_key['parent_table'];

			$this->assertContains( $foreign_key['column'], $columns[ $table ] ?? array(), "{$table}.{$foreign_key['column']} is not defined." );
			$this->assertContains( $foreign_key['parent_column'], $columns[ $parent ] ?? array(), "{$parent}.{$foreign_key['parent_column']} is not defined." );
		}
	}

	/**
	 * The column names of each CREATE TABLE statement, keyed by table name in schema order.
	 *
	 * @return array<string, string[]>
	 */
	private function columns_by_table(): array {
		preg_match_all( '/CREATE TABLE `([^`]+)` \((.*?)\) ENGINE/s', Schema::tables(), $matches, PREG_SET_ORDER );

		$columns = array();
		foreach ( $matches as $match ) {
			preg_match_all( '/^\s*([a-z_]+) [A-Z]/m', $match[2], $names );
			$columns[ $match[1] ] = array_values( array_diff( $names[1], array( 'PRIMARY', 'UNIQUE', 'KEY' ) ) );
		}

		return $columns;
	}
}
