<?php

namespace WPO\OTD\Tests\Integration\Core;

use WPO\OTD\Core\Container\Container;
use WPO\OTD\Core\Providers\CoreServiceProvider;
use WPO\OTD\Models\CustomOrderStatus;
use WPO\OTD\Models\Task;
use WPO\OTD\Models\TaskField;
use WPO\OTD\Models\TaskFieldOption;
use WPO\OTD\Models\TaskFieldValue;
use WPO\OTD\Repositories\CustomOrderStatusRepository;
use WPO\OTD\Repositories\RepositoryRegistry;
use WPO\OTD\Repositories\TaskFieldOptionRepository;
use WPO\OTD\Repositories\TaskFieldRepository;
use WPO\OTD\Repositories\TaskFieldValueRepository;
use WPO\OTD\Repositories\TaskRepository;
use WPO\OTD\Tests\IntegrationTestCase;

class CoreServiceProviderTest extends IntegrationTestCase {
	public function test_register_maps_every_model_to_its_repository(): void {
		$container = new Container();

		( new CoreServiceProvider() )->register( $container );

		$this->assertSame( $container->get( TaskRepository::class ), RepositoryRegistry::get( Task::class ) );
		$this->assertInstanceOf( TaskFieldRepository::class, RepositoryRegistry::get( TaskField::class ) );
		$this->assertInstanceOf( TaskFieldOptionRepository::class, RepositoryRegistry::get( TaskFieldOption::class ) );
		$this->assertInstanceOf( TaskFieldValueRepository::class, RepositoryRegistry::get( TaskFieldValue::class ) );
		$this->assertInstanceOf( CustomOrderStatusRepository::class, RepositoryRegistry::get( CustomOrderStatus::class ) );
	}

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
}
