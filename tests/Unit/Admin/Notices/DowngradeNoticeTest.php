<?php

namespace WPO\OTD\Tests\Unit\Admin\Notices;

use Brain\Monkey\Functions;
use WPO\OTD\Admin\Notices\DowngradeNotice;
use WPO\OTD\Tests\TestCase;

class DowngradeNoticeTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_kses_post' )->returnArg();
	}

	public function test_warns_when_the_data_needs_a_newer_version(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( 2 );

		$output = $this->show();

		$this->assertStringStartsWith( '<div class="notice notice-warning"><p><strong>', $output );
		$this->assertStringContainsString( 'was downgraded.', $output );
	}

	public function test_nothing_is_shown_when_the_data_is_compatible(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( 1 );

		$this->assertSame( '', $this->show() );
	}

	public function test_only_users_who_can_activate_plugins_see_it(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'activate_plugins' )->andReturn( false );
		Functions\when( 'get_option' )->justReturn( 2 );

		$this->assertSame( '', $this->show() );
	}

	/**
	 * What the notice prints.
	 *
	 * @return string
	 */
	private function show(): string {
		ob_start();
		( new DowngradeNotice() )->show();

		return (string) ob_get_clean();
	}
}
