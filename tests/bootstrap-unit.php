<?php
/**
 * PHPUnit bootstrap for the unit suite.
 *
 * Does NOT load WordPress: Brain Monkey stubs the WordPress functions each test needs.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Every plugin file bails out without ABSPATH.
defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__ ) . '/' );

defined( 'WPO_OTD_PLUGIN_FILE' ) || define( 'WPO_OTD_PLUGIN_FILE', dirname( __DIR__ ) . '/out-the-door-order-tasks-workflows-for-woocommerce.php' );
defined( 'WPO_OTD_VERSION' ) || define( 'WPO_OTD_VERSION', \WPO\OTD\OutTheDoor::VERSION );

// wpdb output type the repositories pass to get_results().
defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );
