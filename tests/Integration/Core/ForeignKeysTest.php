<?php

namespace WPO\OTD\Tests\Integration\Core;

use WPO\OTD\Core\ForeignKeys;
use WPO\OTD\Core\Schema;
use WPO\OTD\Tests\IntegrationTestCase;

/**
 * Which foreign keys apply() reports as still missing, and why.
 *
 * Adding and restoring keys for real commits the test's transaction, so InstallerTest covers that.
 */
class ForeignKeysTest extends IntegrationTestCase {
	/**
	 * A key no installed table has: each task pointing at itself, so no row counts as an orphan.
	 */
	private const SELF_REFERENCE = array(
		'table'         => 'wpo_otd_tasks',
		'column'        => 'id',
		'parent_table'  => 'wpo_otd_tasks',
		'parent_column' => 'id',
	);

	public function test_apply_reports_nothing_when_every_key_is_in_place(): void {
		$this->assertSame( array(), ForeignKeys::apply( Schema::foreign_keys() ) );
	}

	public function test_apply_reports_a_key_whose_parent_table_is_missing_without_attempting_it(): void {
		$key = array( 'parent_table' => 'wpo_otd_missing' ) + self::SELF_REFERENCE;

		// Attempting it would commit the transaction, which fails the test.
		$this->assertSame( array( $key + array( 'error' => '' ) ), ForeignKeys::apply( array( $key ) ) );
	}

	public function test_apply_reports_a_key_the_database_rejects_with_its_error(): void {
		$this->fail_queries( '/ADD CONSTRAINT/' );

		$missing = ForeignKeys::apply( array_merge( Schema::foreign_keys(), array( self::SELF_REFERENCE ) ) );

		$this->assertCount( 1, $missing );
		$this->assertSame( self::SELF_REFERENCE, array_diff_key( $missing[0], array( 'error' => true ) ) );
		$this->assertStringContainsString( 'wpo_otd_forced_failure', $missing[0]['error'] );
	}

	public function test_ensure_innodb_accepts_an_innodb_table_and_refuses_a_missing_one(): void {
		$this->assertTrue( ForeignKeys::ensure_innodb( $this->table( 'tasks' ) ) );
		$this->assertFalse( ForeignKeys::ensure_innodb( $this->table( 'missing' ) ) );
	}
}
