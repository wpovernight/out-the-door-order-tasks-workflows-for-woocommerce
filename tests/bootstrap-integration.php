<?php
/**
 * PHPUnit bootstrap for the integration suite.
 *
 * Boots WordPress with WooCommerce and this plugin loaded, then runs both plugins' real
 * activation so every run starts from freshly created tables and seeded defaults.
 * See tests/environment.php for where WordPress comes from.
 */

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$env = require __DIR__ . '/environment.php';

/**
 * Stop the run with a message.
 *
 * @param string $message
 *
 * @return never
 */
$abort = static function ( string $message ): void {
	fwrite( STDERR, PHP_EOL . $message . PHP_EOL . PHP_EOL ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
};

// The suite drops every table in its database, so refuse anything that does not look like a test database.
if ( false === stripos( $env['db_name'], 'test' ) ) {
	$abort( "The test database \"{$env['db_name']}\" does not contain \"test\" in its name. The suite drops every table in it, so pick a dedicated one with WP_TESTS_DB_NAME." );
}

if ( ! file_exists( $env['abspath'] . 'wp-settings.php' ) ) {
	$abort( "WordPress was not found at {$env['abspath']}. Run `composer test:install` to download it, or place this plugin inside a WordPress site next to WooCommerce." );
}

if ( ! file_exists( $env['woocommerce'] ) ) {
	$abort( "WooCommerce was not found at {$env['woocommerce']}. Run `composer test:install`." );
}

// A site's database and object cache drop-ins would load into the tests.
if ( ! $env['standalone'] ) {
	foreach ( array( 'db.php', 'object-cache.php' ) as $dropin ) {
		if ( file_exists( $env['abspath'] . "wp-content/{$dropin}" ) ) {
			$abort( "The site at {$env['abspath']} has a wp-content/{$dropin} drop-in, which would load into the tests. Remove it, or run the standalone copy: `composer test:install`, then set WP_TESTS_STANDALONE=1." );
		}
	}
}

/*
 * Prepare the test database: create it on first use, claim it for this run, and empty it.
 *
 * The named lock makes a second run against the same database (another terminal, another
 * checkout) stop instead of wiping this one's tables mid-run. It is held until this process
 * exits, so the connection is kept open in a global.
 *
 * WordPress's installer only recreates WordPress's own tables, so WooCommerce's (order items,
 * scheduled actions) would carry rows from earlier runs into this one. Every table with the
 * test prefix is dropped instead; activation below recreates WooCommerce's and the plugin's.
 */
$GLOBALS['wpo_otd_tests_db_lock'] = ( static function () use ( $env, $abort ): mysqli {
	mysqli_report( MYSQLI_REPORT_OFF );

	$host   = $env['db_host'];
	$port   = null;
	$socket = null;

	if ( preg_match( '/^(.*):(\d+)$/', $host, $parts ) ) {
		[ , $host, $port ] = $parts;
	} elseif ( preg_match( '/^(.*):(\/.+)$/', $host, $parts ) ) {
		[ , $host, $socket ] = $parts;
	}

	$mysql = @new mysqli( $host, $env['db_user'], $env['db_password'], '', (int) $port ?: null, $socket ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	if ( $mysql->connect_errno ) {
		$abort( "Cannot connect to MySQL at {$env['db_host']} as {$env['db_user']}: {$mysql->connect_error}. Set WP_TESTS_DB_HOST, WP_TESTS_DB_USER and WP_TESTS_DB_PASSWORD." );
	}

	$name = '`' . str_replace( '`', '``', $env['db_name'] ) . '`';

	if ( ! $mysql->query( "CREATE DATABASE IF NOT EXISTS {$name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" ) ) {
		$abort( "Cannot create the test database {$env['db_name']}: {$mysql->error}" );
	}

	$mysql->select_db( $env['db_name'] );

	$lock = $mysql->real_escape_string( substr( 'wpo_otd_tests:' . $env['db_name'], 0, 64 ) );

	if ( '1' !== (string) $mysql->query( "SELECT GET_LOCK('{$lock}', 0)" )->fetch_row()[0] ) {
		$abort( "Another test run is using the database {$env['db_name']}. Wait for it to finish, or give this run its own database with WP_TESTS_DB_NAME." );
	}

	$prefix = getenv( 'WP_PHPUNIT__TABLE_PREFIX' ) ?: 'wptests_';
	$like   = $mysql->real_escape_string( addcslashes( $prefix, '_%\\' ) ) . '%';
	$tables = array_column( $mysql->query( "SHOW TABLES LIKE '{$like}'" )->fetch_all(), 0 );

	if ( $tables ) {
		$mysql->query( 'SET FOREIGN_KEY_CHECKS = 0' );
		$mysql->query( 'DROP TABLE `' . implode( '`, `', $tables ) . '`' );
		$mysql->query( 'SET FOREIGN_KEY_CHECKS = 1' );
	}

	return $mysql;
} )();

if ( false === getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
}

$tests_dir   = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
$plugin_file = dirname( __DIR__ ) . '/out-the-door-order-tasks-workflows-for-woocommerce.php';

// In dependency order: this plugin needs WooCommerce.
$plugin_files = array( $env['woocommerce'], $plugin_file );

require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $plugin_files, $plugin_file ): void {
		global $wp_plugin_paths;

		// Outside a site, the plugin is not in the plugins directory. Map it in, as WordPress does for a
		// symlinked plugin, so plugin_basename() and plugins_url() see it at plugins/<folder>.
		$plugin_dir = wp_normalize_path( dirname( $plugin_file ) );
		$mapped_dir = wp_normalize_path( WP_PLUGIN_DIR . '/' . basename( $plugin_dir ) );

		if ( $plugin_dir !== $mapped_dir ) {
			$wp_plugin_paths[ $mapped_dir ] = $plugin_dir;
		}

		// WooCommerce queries its tables while loading, before its activation below creates them.
		$GLOBALS['wpdb']->suppress_errors( true );

		foreach ( $plugin_files as $file ) {
			require $file;
		}

		\WPO\OTD\Tests\Support\InstalledPlugins::include_this_plugin();
	}
);

require $tests_dir . '/includes/bootstrap.php';

// Core's update checks call WordPress.org on admin_init and warn when external HTTP is blocked (see wp-tests-config.php).
// Removed before the first test backs up the hooks, so they stay removed for the whole run.
remove_action( 'admin_init', '_maybe_update_core' );
remove_action( 'admin_init', '_maybe_update_plugins' );
remove_action( 'admin_init', '_maybe_update_themes' );

// The database was emptied above: activation creates WooCommerce's tables and the plugin's current schema.
foreach ( $plugin_files as $file ) {
	do_action( 'activate_' . plugin_basename( $file ) );
}

$GLOBALS['wpdb']->suppress_errors( false );

// Activation stored WooCommerce's capabilities (manage_woocommerce, ...) in the roles option, but this request
// loaded the roles before that. Reload them, as the next request on a real site would.
wp_roles()->for_site();

// Store orders in WooCommerce's own tables (HPOS) or in posts, as WP_TESTS_HPOS asks.
if ( $env['hpos'] ) {
	wc_get_container()->get( DataSynchronizer::class )->create_database_tables();
}
update_option( CustomOrdersTableController::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION, $env['hpos'] ? 'yes' : 'no' );
update_option( DataSynchronizer::ORDERS_DATA_SYNC_ENABLED_OPTION, 'no' );

printf(
	'WordPress %1$s (%2$s), WooCommerce %3$s, orders in %4$s.' . PHP_EOL,
	get_bloginfo( 'version' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$env['standalone'] ? 'standalone copy' : 'site at ' . $env['abspath'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	WC_VERSION, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$env['hpos'] ? 'HPOS tables' : 'posts' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);

unset( $env, $abort, $tests_dir, $plugin_file, $plugin_files );
