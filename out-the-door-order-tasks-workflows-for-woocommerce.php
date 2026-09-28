<?php
/**
 * Plugin Name:          Out the Door – Order Tasks & Workflows for WooCommerce
 * Requires Plugins:     woocommerce
 * Plugin URI:           https://wpovernight.com/downloads/out-the-door-order-tasks-workflows-for-woocommerce/
 * Description:          A powerful order management plugin for WooCommerce that enhances the order management experience with advanced features.
 * Author:               WP Overnight
 * Author URI:           https://www.wpovernight.com
 * License:              GPLv3
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Version:              1.0.0
 * Requires at least:    6.7
 * Requires PHP:         8.1
 * WC requires at least: 8.2
 * WC tested up to:      11.1
 * Text Domain:          out-the-door-order-tasks-workflows-for-woocommerce
 * Domain Path:          /languages
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WPO_OTD_PLUGIN_FILE' ) ) {
	define( 'WPO_OTD_PLUGIN_FILE', __FILE__ );
}

// Include the main class for the plugin.
if ( ! class_exists( '\\WPO\\OTD\\OutTheDoor' ) ) {
	include_once dirname( WPO_OTD_PLUGIN_FILE ) . '/includes/OutTheDoor.php';
}

/**
 * Get the main instance of WPO_OTD.
 *
 * @return \WPO\OTD\OutTheDoor
 */
function WPO_OTD(): \WPO\OTD\OutTheDoor {
	return \WPO\OTD\OutTheDoor::instance();
}

WPO_OTD();

/**
 * Activation composition root.
 *
 * The activation request never reaches the `plugins_loaded` boot, so wire a dedicated root here.
 */
register_activation_hook(
	WPO_OTD_PLUGIN_FILE,
	static function (): void {
		// Never create tables for an environment the plugin cannot run in.
		if ( ! \WPO\OTD\Core\DependencyChecker::instance()->check_dependencies() ) {
			return;
		}

		$kernel = new \WPO\OTD\Core\Kernel( new \WPO\OTD\Core\Container\Container() );
		$kernel->add_provider( new \WPO\OTD\Core\Providers\CoreServiceProvider() );
		$kernel->register();

		$kernel->container()->get( \WPO\OTD\Core\Installer::class )->install();
	}
);
