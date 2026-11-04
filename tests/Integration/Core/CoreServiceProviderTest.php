<?php

namespace WPO\OTD\Tests\Integration\Core;

use WPO\OTD\Core\Container\Container;
use WPO\OTD\Core\Providers\CoreServiceProvider;
use WPO\OTD\Services\FulfillmentService;
use WPO\OTD\Tests\IntegrationTestCase;

class CoreServiceProviderTest extends IntegrationTestCase {
	public function test_admin_init_runs_a_due_upgrade(): void {
		remove_all_actions( 'admin_init' );
		( new CoreServiceProvider() )->boot( new Container() );

		update_option( 'wpo_otd_db_version', 0 );
		do_action( 'admin_init' );

		$this->assertSame( 1, (int) get_option( 'wpo_otd_db_version' ) );
	}

	public function test_admin_init_leaves_a_current_install_alone(): void {
		remove_all_actions( 'admin_init' );
		( new CoreServiceProvider() )->boot( new Container() );

		update_option( 'wpo_otd_version', '0.9.0' );
		do_action( 'admin_init' );

		$this->assertSame( '0.9.0', get_option( 'wpo_otd_version' ) );
	}

	public function test_admin_screens_are_hooked_in_wp_admin(): void {
		set_current_screen( 'dashboard' );

		( new CoreServiceProvider() )->boot( new Container() );

		$this->assertNotFalse( has_action( 'admin_menu' ) );
		$this->assertNotFalse( has_action( 'add_meta_boxes' ) );
		$this->assertNotFalse( has_action( 'woocommerce_admin_order_item_headers' ) );
		$this->assertNotFalse( has_action( 'wp_ajax_wpo_otd_save_fulfillment' ) );
	}

	public function test_refunds_refresh_the_fulfillment_status(): void {
		$container = new Container();
		( new CoreServiceProvider() )->boot( $container );
		$service = $container->get( FulfillmentService::class );

		$this->assertSame( 10, has_action( 'woocommerce_order_refunded', array( $service, 'refresh_after_refund' ) ) );
		$this->assertSame( 10, has_action( 'woocommerce_refund_deleted', array( $service, 'refresh_after_refund_deleted' ) ) );
	}
}
