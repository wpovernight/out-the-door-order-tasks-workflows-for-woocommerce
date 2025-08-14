<?php
/**
 * Plugin Name:          Advanced Order Manager for WooCommerce
 * Requires Plugins:     woocommerce
 * Plugin URI:           https://wpovernight.com/downloads/advanced-order-manager/
 * Description:          A powerful order management plugin for WooCommerce that enhances the order management experience with advanced features.
 * Author:               WP Overnight
 * Author URI:           https://www.wpovernight.com
 * License:              GPLv3
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Version:              1.0.0
 * Requires at least:    6.7
 * Requires PHP:         7.4
 * WC requires at least: 8.2
 * WC tested up to:      10.0
 * Text Domain:          wpo_aom
 * Domain Path:          /languages
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WPO_AOM_PLUGIN_FILE' ) ) {
	define( 'WPO_AOM_PLUGIN_FILE', __FILE__ );
}

// Include the main class for the plugin.
if ( ! class_exists( '\\WPO\\AOM\\AdvancedOrderManager' ) ) {
	include_once dirname( WPO_AOM_PLUGIN_FILE ) . '/includes/AdvancedOrderManager.php';
}

/**
 * Get the main instance of WPO_AOM.
 *
 * @return \WPO\AOM\AdvancedOrderManager
 */
function WPO_AOM(): \WPO\AOM\AdvancedOrderManager {
	return \WPO\AOM\AdvancedOrderManager::instance();
}

WPO_AOM();
