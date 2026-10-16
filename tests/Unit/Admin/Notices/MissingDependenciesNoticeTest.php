<?php

namespace WPO\OTD\Tests\Unit\Admin\Notices;

use Brain\Monkey\Functions;
use WPO\OTD\Admin\Notices\MissingDependenciesNotice;
use WPO\OTD\Tests\TestCase;

class MissingDependenciesNoticeTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_kses_post' )->returnArg();
	}

	public function test_lists_and_escapes_every_unmet_requirement_as_an_error(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$this->assertSame(
			'<div class="notice notice-error"><p><strong>Out the Door – Order Tasks &amp; Workflows for WooCommerce</strong> can’t run because:</p>'
			. '<ul><li>First &lt;b&gt;problem&lt;/b&gt;</li><li>Second problem</li></ul></div>',
			$this->show( new MissingDependenciesNotice( array( 'First <b>problem</b>', 'Second problem' ) ) )
		);
	}

	public function test_only_users_who_can_activate_plugins_see_it(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'activate_plugins' )->andReturn( false );

		$this->assertSame( '', $this->show( new MissingDependenciesNotice( array( 'WooCommerce must be activated.' ) ) ) );
	}

	public function test_nothing_is_shown_without_an_unmet_requirement(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$this->assertSame( '', $this->show( new MissingDependenciesNotice( array() ) ) );
	}

	/**
	 * What a notice prints.
	 *
	 * @param MissingDependenciesNotice $notice
	 *
	 * @return string
	 */
	private function show( MissingDependenciesNotice $notice ): string {
		ob_start();
		$notice->show();

		return (string) ob_get_clean();
	}
}
