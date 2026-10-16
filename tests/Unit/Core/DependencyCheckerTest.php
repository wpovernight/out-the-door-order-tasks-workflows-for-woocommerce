<?php

namespace WPO\OTD\Tests\Unit\Core;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use WPO\OTD\Admin\Notices\MissingDependenciesNotice;
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
		Functions\when( 'current_user_can' )->justReturn( true );
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

		$output = $this->show( $notice );

		$this->assertStringContainsString( '<div class="notice notice-error">', $output );
		$this->assertStringContainsString( '<li>WooCommerce must be activated.</li>', $output );
		$this->assertStringNotContainsString( 'PHP 8.1+ is required.', $output );
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

		$this->assertStringContainsString( '<li>WooCommerce 8.2+ is required.</li>', $this->show( $notice ) );
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

	/**
	 * What the notice queued on admin_notices prints.
	 *
	 * @param mixed $callback The queued callback.
	 *
	 * @return string
	 */
	private function show( $callback ): string {
		$this->assertIsArray( $callback );
		$this->assertInstanceOf( MissingDependenciesNotice::class, $callback[0] );

		ob_start();
		$callback();

		return (string) ob_get_clean();
	}
}
