<?php

namespace WPO\OTD\Tests\Unit\Core;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use WPO\OTD\Core\DependencyChecker;
use WPO\OTD\Tests\TestCase;

/**
 * WooCommerce is not loaded in the unit suite, so the "missing WooCommerce" path runs in-process.
 * Paths that need a WooCommerce class and version constant run in a separate process.
 */
class DependencyCheckerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->set_static_property( DependencyChecker::class, '_instance', null );

		Functions\when( 'wp_kses_post' )->returnArg();
	}

	public function test_instance_is_a_singleton(): void {
		$this->assertSame( DependencyChecker::instance(), DependencyChecker::instance() );
	}

	public function test_fails_and_queues_a_notice_when_woocommerce_is_missing(): void {
		$notice = null;

		Actions\expectAdded( 'admin_notices' )->once()->whenHappen(
			static function ( $callback ) use ( &$notice ) {
				$notice = $callback;
			}
		);

		$this->assertFalse( DependencyChecker::instance()->check_dependencies() );

		ob_start();
		$notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div class="notice notice-error">', $output );
		$this->assertStringContainsString( '<li>WooCommerce must be activated.</li>', $output );
		$this->assertStringNotContainsString( 'PHP 8.1+ is required.', $output );
	}

	public function test_admin_notice_lists_and_escapes_every_error(): void {
		ob_start();
		DependencyChecker::instance()->display_admin_notice( array( 'First <b>problem</b>', 'Second problem' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Out the Door – Order Tasks &amp; Workflows for WooCommerce', $output );
		$this->assertStringContainsString( '<ul><li>First &lt;b&gt;problem&lt;/b&gt;</li><li>Second problem</li></ul>', $output );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_fails_when_woocommerce_is_too_old(): void {
		eval( 'class WooCommerce {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Declares a stand-in class for this process only.
		define( 'WC_VERSION', '8.1.9' );

		$notice = null;
		Actions\expectAdded( 'admin_notices' )->once()->whenHappen(
			static function ( $callback ) use ( &$notice ) {
				$notice = $callback;
			}
		);

		$this->assertFalse( DependencyChecker::instance()->check_dependencies() );

		ob_start();
		$notice();
		$this->assertStringContainsString( '<li>WooCommerce 8.2+ is required.</li>', ob_get_clean() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_passes_with_a_supported_woocommerce(): void {
		eval( 'class WooCommerce {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Declares a stand-in class for this process only.
		define( 'WC_VERSION', '8.2.0' );

		Actions\expectAdded( 'admin_notices' )->never();

		$this->assertTrue( DependencyChecker::instance()->check_dependencies() );
	}
}
