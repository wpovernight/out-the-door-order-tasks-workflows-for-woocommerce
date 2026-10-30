<?php

namespace WPO\OTD\Tests\Integration\Admin;

use WPO\OTD\Admin\Assets;
use WPO\OTD\Admin\OrderManager\Screen;
use WPO\OTD\Tests\IntegrationTestCase;

class OrderManagerScreenTest extends IntegrationTestCase {
	private Screen $screen;

	public function set_up() {
		parent::set_up();

		$this->screen = $this->make( Screen::class );

		// Start every test without the runtime registered by an earlier one.
		wp_deregister_script( Assets::SDK_HANDLE );
		wp_deregister_script( Assets::ROUTER_HANDLE );
	}

	public function tear_down() {
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;

		parent::tear_down();
	}

	public function test_hooks_into_the_admin(): void {
		$this->screen->register_hooks();

		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', array( $this->screen, 'enqueue_scripts' ) ) );
		$this->assertSame( 99, has_action( 'admin_menu', array( $this->screen, 'add_screen' ) ) );
	}

	public function test_adds_a_woocommerce_submenu_for_managers(): void {
		global $submenu;

		$this->act_as( 'shop_manager' );
		$this->screen->add_screen();

		$this->assertContains( 'wpo_otd_order_manager', array_column( $submenu['woocommerce'] ?? array(), 2 ) );
	}

	public function test_the_task_board_is_the_kanban_view_of_its_page(): void {
		$this->assertSame( 'wpo_otd_order_manager', Screen::PAGE_SLUG );
		$this->assertSame( admin_url( 'admin.php?page=wpo_otd_order_manager#/task-manager/kanban' ), Screen::task_board_url() );
	}

	public function test_renders_the_app_root_for_managers_only(): void {
		$this->act_as( 'subscriber' );
		ob_start();
		$this->screen->render_page();
		$this->assertSame( '', ob_get_clean() );

		$this->act_as( 'shop_manager' );
		ob_start();
		$this->screen->render_page();
		$this->assertSame( '<div id="wpo-otd-order-manager"></div>', ob_get_clean() );
	}

	public function test_assets_are_only_enqueued_on_its_own_screen(): void {
		set_current_screen( 'dashboard' );

		$this->screen->enqueue_scripts();

		$this->assertFalse( wp_script_is( 'wpo-otd-admin-order-manager', 'enqueued' ) );
	}

	public function test_enqueues_the_app_with_its_settings(): void {
		set_current_screen( 'woocommerce_page_wpo_otd_order_manager' );
		add_filter( 'wpo_otd_order_manager_script_deps', static fn( $deps ) => array_merge( $deps, array( 'extra-dep' ) ) );

		$this->screen->enqueue_scripts();

		$script = wp_scripts()->registered['wpo-otd-admin-order-manager'];
		$this->assertTrue( wp_script_is( 'wpo-otd-admin-order-manager', 'enqueued' ) );
		$this->assertStringEndsWith( '/assets/js/order-manager.js', $script->src );
		$this->assertSame( array_merge( Assets::runtime_dependencies( true ), array( 'extra-dep' ) ), $script->deps );

		$settings = $this->localized( 'wpo-otd-admin-order-manager', 'WPO_OTD_OrderManager' );
		$this->assertSame( 'wpo/otd', $settings['apiNamespace'] );
		$this->assertStringEndsWith( '/wc/v3', $settings['apiRoot'] );
		$this->assertSame( (string) $this->status_option_id( 'done' ), (string) $settings['statusRoles']['done'] );

		foreach ( array( 'common', 'order-manager', 'task-card', 'sidebar-modal', 'dashboard', 'task-manager', 'custom-order-status', 'skeleton' ) as $style ) {
			$this->assertTrue( wp_style_is( "wpo-otd-admin-{$style}", 'enqueued' ), "Style {$style} is not enqueued." );
		}
		$this->assertStringEndsWith( '/assets/css/common.min.css', wp_styles()->registered['wpo-otd-admin-common']->src );
	}

	public function test_the_runtime_is_registered_once(): void {
		Assets::register_runtime();
		$sdk = wp_scripts()->registered[ Assets::SDK_HANDLE ];

		Assets::register_runtime();

		$this->assertSame( $sdk, wp_scripts()->registered[ Assets::SDK_HANDLE ] );
		$this->assertSame( array( 'wp-hooks', 'wp-element', 'wp-components', 'wp-i18n' ), $sdk->deps );
		$this->assertSame( array( 'wp-element' ), wp_scripts()->registered[ Assets::ROUTER_HANDLE ]->deps );
		$this->assertSame( 'out-the-door-order-tasks-workflows-for-woocommerce', $sdk->textdomain );
	}

	public function test_runtime_dependencies(): void {
		$this->assertSame( array( 'wp-hooks', 'wp-element', 'wp-components', 'wp-i18n', Assets::SDK_HANDLE ), Assets::runtime_dependencies() );
		$this->assertSame( array( 'wp-hooks', 'wp-element', 'wp-components', 'wp-i18n', Assets::SDK_HANDLE, Assets::ROUTER_HANDLE ), Assets::runtime_dependencies( true ) );
	}

	/**
	 * Decode the object a script was localized with.
	 *
	 * @param string $handle
	 * @param string $object_name
	 *
	 * @return array
	 */
	private function localized( string $handle, string $object_name ): array {
		$data = wp_scripts()->get_data( $handle, 'data' );

		$this->assertMatchesRegularExpression( "/var {$object_name} = (.+);/", $data );
		preg_match( "/var {$object_name} = (.+);/", $data, $matches );

		return json_decode( $matches[1], true );
	}
}
