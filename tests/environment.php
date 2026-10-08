<?php
/**
 * Where the integration suite finds WordPress, WooCommerce and its database.
 *
 * Shared by the bootstrap and wp-tests-config.php, which WordPress also loads in the child
 * process that installs it, so this file must not depend on WordPress or the autoloader.
 *
 * WordPress comes from, in order:
 *  1. WP_TESTS_ABSPATH, when set.
 *  2. The site this plugin sits in, when it is inside wp-content/plugins/ next to WooCommerce.
 *  3. The standalone copy in build/wordpress/, downloaded by `composer test:install`.
 *     Set WP_TESTS_STANDALONE=1 to use it even inside a site.
 *
 * @return array{abspath: string, plugins_dir: string, woocommerce: string, standalone: bool, hpos: bool, db_name: string, db_user: string, db_password: string, db_host: string}
 */

return ( static function (): array {
	$plugin_root     = dirname( __DIR__ );
	$site_root       = dirname( $plugin_root, 3 );
	$standalone_root = $plugin_root . '/build/wordpress';

	$abspath    = getenv( 'WP_TESTS_ABSPATH' );
	$standalone = false;

	if ( ! $abspath ) {
		$inside_site = file_exists( $site_root . '/wp-settings.php' )
			&& file_exists( dirname( $plugin_root ) . '/woocommerce/woocommerce.php' );

		$standalone = ! $inside_site || filter_var( getenv( 'WP_TESTS_STANDALONE' ), FILTER_VALIDATE_BOOLEAN );
		$abspath    = $standalone ? $standalone_root : $site_root;
	}

	$abspath     = rtrim( str_replace( '\\', '/', $abspath ), '/' ) . '/';
	$plugins_dir = $abspath . 'wp-content/plugins';

	return array(
		'abspath'     => $abspath,
		'plugins_dir' => $plugins_dir,
		'woocommerce' => $plugins_dir . '/woocommerce/woocommerce.php',
		'standalone'  => $standalone,
		'hpos'        => filter_var( getenv( 'WP_TESTS_HPOS' ), FILTER_VALIDATE_BOOLEAN ),
		'db_name'     => getenv( 'WP_TESTS_DB_NAME' ) ?: 'wpo_otd_tests',
		'db_user'     => getenv( 'WP_TESTS_DB_USER' ) ?: 'root',
		'db_password' => getenv( 'WP_TESTS_DB_PASSWORD' ) ?: '',
		'db_host'     => getenv( 'WP_TESTS_DB_HOST' ) ?: '127.0.0.1',
	);
} )();
