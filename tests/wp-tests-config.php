<?php
/**
 * WordPress test-suite configuration.
 *
 * The values come from tests/environment.php: WordPress is either the site this plugin sits in or
 * the standalone copy from `composer test:install`, always with its own database, which the test
 * suite empties on every run. Override any value with the matching WP_TESTS_* environment variable.
 */

$wpo_otd_env = require __DIR__ . '/environment.php';

define( 'ABSPATH', $wpo_otd_env['abspath'] );

define( 'DB_NAME', $wpo_otd_env['db_name'] );
define( 'DB_USER', $wpo_otd_env['db_user'] );
define( 'DB_PASSWORD', $wpo_otd_env['db_password'] );
define( 'DB_HOST', $wpo_otd_env['db_host'] );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

// Never load a site's must-use plugins into the tests.
define( 'WPMU_PLUGIN_DIR', __DIR__ . '/mu-plugins' );

// The suite installs WordPress in a child process: use this PHP, not whichever is first on PATH.
define( 'WP_PHP_BINARY', PHP_BINARY );

// Keep the tests off the network: WooCommerce fetches remote data on admin_init, which made each AJAX test take seconds.
define( 'WP_HTTP_BLOCK_EXTERNAL', true );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_DEBUG', true );

unset( $wpo_otd_env );
