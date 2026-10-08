<?php

namespace WPO\OTD\Tests\Integration\Support;

use WPO\OTD\Tests\IntegrationTestCase;

/**
 * The transaction handling in IntegrationTestCase, which every other integration test relies on.
 */
class TestIsolationTest extends IntegrationTestCase {
	/**
	 * @dataProvider transaction_statement_provider
	 */
	public function test_transaction_statements_become_savepoints( string $start, string $end, string $expected_end ): void {
		$savepoint = $this->nest_plugin_transactions( $start );

		$this->assertMatchesRegularExpression( '/^SAVEPOINT wpo_otd_test_\d+$/', $savepoint );
		$this->assertSame( $expected_end . ' ' . substr( $savepoint, strlen( 'SAVEPOINT ' ) ), $this->nest_plugin_transactions( $end ) );
	}

	public function transaction_statement_provider(): array {
		return array(
			'as the plugin writes them' => array( 'START TRANSACTION', 'COMMIT', 'RELEASE SAVEPOINT' ),
			'rolled back'               => array( 'START TRANSACTION', 'ROLLBACK', 'ROLLBACK TO SAVEPOINT' ),
			'lowercase with semicolons' => array( 'start transaction;', 'commit;', 'RELEASE SAVEPOINT' ),
			'BEGIN and WORK'            => array( "  BEGIN\n", 'ROLLBACK WORK', 'ROLLBACK TO SAVEPOINT' ),
		);
	}

	public function test_nested_transactions_unwind_innermost_first(): void {
		$outer = substr( $this->nest_plugin_transactions( 'START TRANSACTION' ), strlen( 'SAVEPOINT ' ) );
		$inner = substr( $this->nest_plugin_transactions( 'START TRANSACTION' ), strlen( 'SAVEPOINT ' ) );

		$this->assertSame( "ROLLBACK TO SAVEPOINT {$inner}", $this->nest_plugin_transactions( 'ROLLBACK' ) );
		$this->assertSame( "RELEASE SAVEPOINT {$outer}", $this->nest_plugin_transactions( 'COMMIT' ) );
	}

	/**
	 * @dataProvider committing_statement_provider
	 */
	public function test_statements_that_would_commit_the_test_are_refused( string $statement ): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( "This statement would commit the test's transaction" );

		$this->nest_plugin_transactions( $statement );
	}

	public function committing_statement_provider(): array {
		return array(
			'commit outside a plugin transaction'   => array( 'COMMIT' ),
			'rollback outside a plugin transaction' => array( 'ROLLBACK;' ),
			'alter'                                 => array( 'ALTER TABLE wp_x ADD COLUMN y INT' ),
			'create'                                => array( 'CREATE TABLE wp_x (id INT)' ),
			'drop'                                  => array( 'DROP TABLE wp_x' ),
			'truncate'                              => array( 'truncate table wp_x' ),
			'rename'                                => array( 'RENAME TABLE wp_x TO wp_y' ),
			'autocommit'                            => array( 'SET autocommit = 1' ),
			'lock tables'                           => array( 'LOCK TABLES wp_x WRITE' ),
		);
	}

	public function test_harmless_statements_pass_through(): void {
		foreach ( array( 'SELECT 1', 'CREATE TEMPORARY TABLE wp_x (id INT)', 'DROP TEMPORARY TABLE wp_x', 'SET SESSION sql_mode = \'\'' ) as $statement ) {
			$this->assertSame( $statement, $this->nest_plugin_transactions( $statement ) );
		}
	}

	public function test_the_guard_is_lifted_once_a_test_allows_real_ddl(): void {
		$this->allow_real_ddl();

		$this->assertSame( 'TRUNCATE TABLE wp_x', $this->nest_plugin_transactions( 'TRUNCATE TABLE wp_x' ) );
	}

	public function test_the_guard_reaches_queries_run_through_wpdb(): void {
		global $wpdb;

		$this->expectException( \LogicException::class );

		$wpdb->query( 'COMMIT' );
	}
}
