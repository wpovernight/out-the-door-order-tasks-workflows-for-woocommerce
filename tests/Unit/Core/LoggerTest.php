<?php

namespace WPO\OTD\Tests\Unit\Core;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use WPO\OTD\Core\Logger;
use WPO\OTD\Tests\TestCase;

class LoggerTest extends TestCase {
	/**
	 * @var Mockery\MockInterface
	 */
	private $wc_logger;

	protected function setUp(): void {
		parent::setUp();

		// The WooCommerce logger is memoized in a static property.
		$this->set_static_property( Logger::class, 'wc_logger', null );

		$this->wc_logger = Mockery::mock( 'WC_Logger_Interface' );
		Functions\when( 'wc_get_logger' )->justReturn( $this->wc_logger );
	}

	protected function tearDown(): void {
		$this->set_static_property( Logger::class, 'wc_logger', null );

		parent::tearDown();
	}

	/**
	 * @dataProvider level_provider
	 */
	public function test_each_level_method_logs_at_its_level( string $level ): void {
		$this->wc_logger->shouldReceive( 'log' )->once()->with( $level, 'Something happened', array( 'source' => 'wpo-otd' ) );

		Logger::$level( 'Something happened' );
	}

	public function level_provider(): array {
		return array_map(
			static fn( $level ) => array( $level ),
			array(
				'debug'     => 'debug',
				'info'      => 'info',
				'notice'    => 'notice',
				'warning'   => 'warning',
				'error'     => 'error',
				'critical'  => 'critical',
				'alert'     => 'alert',
				'emergency' => 'emergency',
			)
		);
	}

	public function test_the_filter_receives_level_and_message(): void {
		Filters\expectApplied( 'wpo_otd_logging_enabled' )->once()->with( true, 'error', 'Broken' )->andReturn( true );
		$this->wc_logger->shouldReceive( 'log' )->once();

		Logger::error( 'Broken' );
	}

	public function test_the_filter_can_disable_logging(): void {
		Filters\expectApplied( 'wpo_otd_logging_enabled' )->once()->andReturn( false );
		$this->wc_logger->shouldNotReceive( 'log' );

		Logger::error( 'Muted' );
	}

	public function test_empty_messages_are_ignored(): void {
		Filters\expectApplied( 'wpo_otd_logging_enabled' )->never();
		$this->wc_logger->shouldNotReceive( 'log' );

		Logger::log( 'error', '' );
	}

	public function test_unknown_levels_are_ignored(): void {
		Filters\expectApplied( 'wpo_otd_logging_enabled' )->never();
		$this->wc_logger->shouldNotReceive( 'log' );

		Logger::log( 'verbose', 'Not a PSR-3 level' );
	}

	public function test_the_woocommerce_logger_is_fetched_once(): void {
		$fetches = 0;
		Functions\when( 'wc_get_logger' )->alias(
			function () use ( &$fetches ) {
				++$fetches;

				return $this->wc_logger;
			}
		);
		$this->wc_logger->shouldReceive( 'log' )->twice();

		Logger::info( 'First' );
		Logger::info( 'Second' );

		$this->assertSame( 1, $fetches );
	}
}
