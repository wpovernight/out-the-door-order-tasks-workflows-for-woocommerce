<?php

namespace WPO\OTD\Tests\Unit\Repositories;

use Brain\Monkey\Functions;
use Mockery;
use WPO\OTD\Core\Logger;
use WPO\OTD\Repositories\BaseRepository;
use WPO\OTD\Repositories\TaskFieldRepository;
use WPO\OTD\Tests\TestCase;

class TaskFieldRepositoryTest extends TestCase {
	/**
	 * @var Mockery\MockInterface
	 */
	private $wpdb;

	/**
	 * @var Mockery\MockInterface
	 */
	private $wc_logger;

	protected function setUp(): void {
		parent::setUp();

		$this->set_static_property( BaseRepository::class, 'cache', array() );
		$this->set_static_property( Logger::class, 'wc_logger', null );
		$this->wc_logger = Mockery::mock( 'WC_Logger_Interface' );
		Functions\when( 'wc_get_logger' )->justReturn( $this->wc_logger );

		$this->wpdb             = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix     = 'wp_';
		$this->wpdb->last_error = '';
		$GLOBALS['wpdb']        = $this->wpdb;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		$this->set_static_property( BaseRepository::class, 'cache', array() );
		$this->set_static_property( Logger::class, 'wc_logger', null );

		parent::tearDown();
	}

	public function test_a_row_with_an_unknown_type_loads_as_text_and_is_logged(): void {
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( array( $this->row( 'user' ) ) );
		$this->wc_logger->shouldReceive( 'log' )
			->once()
			->with( 'error', 'Invalid field type "user" for field "creator". Defaulting to text.', Mockery::any() );

		$fields = ( new TaskFieldRepository() )->get();

		$this->assertSame( 'text', $fields[0]->type );
	}

	public function test_a_row_with_a_known_type_is_not_logged(): void {
		$this->wpdb->shouldReceive( 'get_results' )->andReturn( array( $this->row( 'select' ) ) );
		$this->wc_logger->shouldNotReceive( 'log' );

		$fields = ( new TaskFieldRepository() )->get();

		$this->assertSame( 'select', $fields[0]->type );
	}

	/**
	 * A task_fields row.
	 *
	 * @param string $type
	 *
	 * @return array<string, mixed>
	 */
	private function row( string $type ): array {
		return array(
			'id'    => '7',
			'label' => 'Creator',
			'type'  => $type,
			'slug'  => 'creator',
		);
	}
}
