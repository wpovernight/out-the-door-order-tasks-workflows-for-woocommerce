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

	public function test_a_message_logged_once_a_day_is_logged_the_first_time_and_marked_for_a_day(): void {
		$marked = array();
		Functions\expect( 'get_transient' )->once()->with( 'wpo_otd_example_mark' )->andReturn( false );
		Functions\expect( 'set_transient' )->once()->andReturnUsing(
			static function ( string $name, $value, int $expiration ) use ( &$marked ): bool {
				$marked[ $name ] = $expiration;

				return true;
			}
		);
		$this->wc_logger->shouldReceive( 'log' )->once()->with( 'warning', 'Something happened', array( 'source' => 'wpo-otd' ) );

		Logger::once( 'wpo_otd_example_mark', 'warning', 'Something happened' );

		$this->assertSame( array( 'wpo_otd_example_mark' => DAY_IN_SECONDS ), $marked );
	}

	public function test_a_message_already_logged_today_is_not_logged_again(): void {
		Functions\expect( 'get_transient' )->once()->with( 'wpo_otd_example_mark' )->andReturn( 1 );
		Functions\expect( 'set_transient' )->never();
		$this->wc_logger->shouldNotReceive( 'log' );

		Logger::once( 'wpo_otd_example_mark', 'error', 'Something happened' );
	}

	public function test_a_message_logged_once_a_day_is_marked_even_while_logging_is_off(): void {
		Functions\expect( 'get_transient' )->once()->andReturn( false );
		Functions\expect( 'set_transient' )->once()->andReturn( true );
		Filters\expectApplied( 'wpo_otd_logging_enabled' )->once()->andReturn( false );
		$this->wc_logger->shouldNotReceive( 'log' );

		Logger::once( 'wpo_otd_example_mark', 'notice', 'Muted' );
	}

	public function test_a_message_that_would_not_be_logged_at_all_is_not_marked(): void {
		Functions\expect( 'get_transient' )->never();
		Functions\expect( 'set_transient' )->never();
		$this->wc_logger->shouldNotReceive( 'log' );

		Logger::once( 'wpo_otd_example_mark', 'verbose', 'Not a PSR-3 level' );
		Logger::once( 'wpo_otd_example_mark', 'error', '' );
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
