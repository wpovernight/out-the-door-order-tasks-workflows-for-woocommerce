<?php

namespace WPO\OTD\Tests\Integration\Core;

use Automattic\WooCommerce\Internal\Features\FeaturesController;
use WPO\OTD\Core\Container\Container;
use WPO\OTD\Core\Container\ServiceProvider;
use WPO\OTD\Core\DependencyChecker;
use WPO\OTD\OutTheDoor;
use WPO\OTD\Tests\IntegrationTestCase;

/**
 * The plugin boots inside a real WordPress + WooCommerce request.
 */
class PluginBootTest extends IntegrationTestCase {
	public function test_main_function_returns_the_singleton(): void {
		$this->assertInstanceOf( OutTheDoor::class, WPO_OTD() );
		$this->assertSame( WPO_OTD(), OutTheDoor::instance() );
		$this->assertSame( OutTheDoor::VERSION, WPO_OTD_VERSION );
	}

	public function test_plugin_path_and_url_point_at_the_plugin_root(): void {
		$this->assertSame( wp_normalize_path( dirname( WPO_OTD_PLUGIN_FILE ) ), wp_normalize_path( WPO_OTD()->plugin_path() ) );
		$this->assertSame( plugins_url() . '/' . basename( dirname( WPO_OTD_PLUGIN_FILE ) ), WPO_OTD()->plugin_url() );
	}

	public function test_dependencies_are_met_with_woocommerce_loaded(): void {
		$this->assertTrue( DependencyChecker::instance()->check_dependencies() );
	}

	public function test_rest_routes_are_registered(): void {
		$routes = rest_get_server()->get_routes();

		foreach (
			array(
				'/wc/v3/wpo/otd/tasks',
				'/wc/v3/wpo/otd/tasks/status-roles',
				'/wc/v3/wpo/otd/tasks/(?P<id>[\d]+)',
				'/wc/v3/wpo/otd/tasks/(?P<id>[\d]+)/move',
				'/wc/v3/wpo/otd/tasks/(?P<id>[\d]+)/finish',
				'/wc/v3/wpo/otd/tasks/(?P<id>[\d]+)/archive',
				'/wc/v3/wpo/otd/tasks/(?P<id>[\d]+)/unarchive',
				'/wc/v3/wpo/otd/tasks/fields',
				'/wc/v3/wpo/otd/tasks/fields/(?P<field_id>[\d]+)/options',
				'/wc/v3/wpo/otd/tasks/fields/(?P<field_id>[\d]+)/options/(?P<option_id>[\d]+)',
				'/wc/v3/wpo/otd/tasks/fields/(?P<field_slug>[\w-]+)/options',
				'/wc/v3/wpo/otd/tasks/fields/(?P<field_id>[\d]+)/options/reorder',
				'/wc/v3/wpo/otd/fulfillments/orders',
				'/wc/v3/wpo/otd/custom-order-statuses',
				'/wc/v3/wpo/otd/custom-order-statuses/(?P<id>\d+)',
			) as $route
		) {
			$this->assertArrayHasKey( $route, $routes, "Route {$route} is not registered." );
		}
	}

	public function test_services_hook_into_wordpress_and_woocommerce(): void {
		$this->assertNotFalse( has_action( 'wpo_otd_rebalance_task_positions' ) );
		$this->assertNotFalse( has_action( 'wpo_otd_reassign_orders' ) );
		$this->assertNotFalse( has_filter( 'wc_order_statuses' ) );
		$this->assertNotFalse( has_filter( 'woocommerce_register_shop_order_post_statuses' ) );
		$this->assertNotFalse( has_filter( 'woocommerce_email_classes' ) );
		$this->assertNotFalse( has_filter( 'woocommerce_email_actions' ) );
		$this->assertNotFalse( has_action( 'wpo_otd_email_task_details' ) );
		$this->assertNotFalse( has_action( 'admin_init' ) );
	}

	public function test_admin_screens_are_not_hooked_on_the_front_end(): void {
		$this->assertFalse( has_action( 'woocommerce_admin_order_item_headers' ) );
		$this->assertFalse( has_action( 'wp_ajax_wpo_otd_save_fulfillment' ) );
	}

	public function test_activation_hook_is_registered(): void {
		$this->assertNotFalse( has_action( 'activate_' . plugin_basename( WPO_OTD_PLUGIN_FILE ) ) );
	}

	public function test_hpos_compatibility_is_declared(): void {
		// Asked per plugin: the per-feature list only includes plugins found in the plugins directory.
		$features = wc_get_container()->get( FeaturesController::class )->get_compatible_features_for_plugin( plugin_basename( WPO_OTD_PLUGIN_FILE ) );

		$this->assertContains( 'custom_order_tables', $features['compatible'] );
	}

	public function test_boot_kernel_registers_and_boots_filtered_providers(): void {
		$provider = new class() implements ServiceProvider {
			public bool $registered = false;
			public bool $booted     = false;

			public function register( Container $container ): void {
				$this->registered = true;
			}

			public function boot( Container $container ): void {
				$this->booted = true;
			}
		};

		add_filter( 'wpo_otd_service_providers', static fn( array $providers ) => array_merge( $providers, array( $provider ) ) );

		WPO_OTD()->boot_kernel();

		$this->assertTrue( $provider->registered );
		$this->assertTrue( $provider->booted );
	}

	/**
	 * @expectedIncorrectUsage WPO\OTD\OutTheDoor::boot_kernel
	 */
	public function test_boot_kernel_rejects_filtered_entries_that_are_not_providers(): void {
		add_filter( 'wpo_otd_service_providers', static fn() => array( new \stdClass() ) );

		WPO_OTD()->boot_kernel();
	}

	public function test_translations_load_the_custom_and_the_plugin_language_files(): void {
		$loaded = array();

		add_filter( 'plugin_locale', static fn() => 'nl_NL' );
		add_action(
			'load_textdomain',
			static function ( $domain, $mofile ) use ( &$loaded ) {
				$loaded[ $domain ][] = $mofile;
			},
			10,
			2
		);

		WPO_OTD()->translations();

		$this->assertSame(
			array(
				WP_LANG_DIR . '/out-the-door-order-tasks-workflows-for-woocommerce/out-the-door-order-tasks-workflows-for-woocommerce-nl_NL.mo',
				WP_LANG_DIR . '/plugins/out-the-door-order-tasks-workflows-for-woocommerce-nl_NL.mo',
			),
			$loaded['out-the-door-order-tasks-workflows-for-woocommerce']
		);
	}
}
