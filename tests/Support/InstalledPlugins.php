<?php

namespace WPO\OTD\Tests\Support;

/**
 * Makes WordPress list this plugin as installed when it sits outside the plugins directory.
 *
 * With the standalone copy of WordPress, the bootstrap maps the plugin into plugins/ so that
 * plugin_basename() and plugins_url() work, but it is not physically there: get_plugins() misses
 * it, and WooCommerce ignores the feature compatibility (HPOS) it declares. get_plugins() serves
 * its result from the object cache, which the test suite flushes before every test, so the plugin
 * is added to that cached list at boot and again at the start of each test.
 */
final class InstalledPlugins {
	/**
	 * Add this plugin to get_plugins()'s cached list, when it is not already there.
	 *
	 * @return void
	 */
	public static function include_this_plugin(): void {
		$basename = plugin_basename( WPO_OTD_PLUGIN_FILE );

		// Inside a site the plugin is really there; listing a site's plugins before every test is slow.
		if ( file_exists( WP_PLUGIN_DIR . '/' . $basename ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugins = get_plugins();

		$plugins[ $basename ] = get_plugin_data( WPO_OTD_PLUGIN_FILE, false, false );

		$cache     = wp_cache_get( 'plugins', 'plugins' ) ?: array();
		$cache[''] = $plugins;

		wp_cache_set( 'plugins', $cache, 'plugins' );
	}
}
