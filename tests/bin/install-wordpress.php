<?php
/**
 * Download WordPress and WooCommerce into build/wordpress/ for the integration suite.
 *
 * WordPress matches the installed wp-phpunit/wp-phpunit version, so the core files and the test
 * library always agree. WooCommerce is the latest stable release, or WOOCOMMERCE_VERSION.
 * Components already in place are kept; pass --force to download them again.
 *
 * Usage:
 *   composer test:install
 *   composer test:install -- --force
 *   WOOCOMMERCE_VERSION=9.8.5 composer test:install
 */

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$build_dir   = dirname( __DIR__, 2 ) . '/build';
$wp_dir      = $build_dir . '/wordpress';
$wc_dir      = $wp_dir . '/wp-content/plugins/woocommerce';
$force       = in_array( '--force', $argv, true );
$wp_version  = ltrim( (string) \Composer\InstalledVersions::getPrettyVersion( 'wp-phpunit/wp-phpunit' ), 'v' );
$wc_version  = getenv( 'WOOCOMMERCE_VERSION' ) ?: 'latest-stable';

if ( '' === $wp_version ) {
	fail( 'wp-phpunit/wp-phpunit is not installed. Run `composer install` first.' );
}

if ( $force ) {
	remove_directory( $wp_dir );
}

if ( installed_version( $wp_dir . '/wp-includes/version.php', '/\$wp_version = \'([^\']+)\'/' ) === $wp_version ) {
	echo "WordPress {$wp_version} is already in place.\n";
} else {
	echo "Downloading WordPress {$wp_version}...\n";

	// Replace core but keep wp-content, which holds WooCommerce.
	foreach ( array( 'wp-admin', 'wp-includes' ) as $core_dir ) {
		remove_directory( "{$wp_dir}/{$core_dir}" );
	}

	extract_archive( download( "https://wordpress.org/wordpress-{$wp_version}.zip" ), $build_dir );
}

$wc_installed = installed_version( $wc_dir . '/woocommerce.php', '/^\s*\*\s*Version:\s*(\S+)/m' );

if ( null !== $wc_installed && ( 'latest-stable' === $wc_version || $wc_installed === $wc_version ) ) {
	echo "WooCommerce {$wc_installed} is already in place.\n";
} else {
	echo "Downloading WooCommerce {$wc_version}...\n";

	remove_directory( $wc_dir );
	extract_archive( download( "https://downloads.wordpress.org/plugin/woocommerce.{$wc_version}.zip" ), dirname( $wc_dir ) );

	echo 'Installed WooCommerce ' . installed_version( $wc_dir . '/woocommerce.php', '/^\s*\*\s*Version:\s*(\S+)/m' ) . ".\n";
}

echo "Done: {$wp_dir}\n";

/**
 * Read a version number from a file, or null when the file is missing.
 *
 * @param string $file
 * @param string $pattern Regular expression capturing the version.
 *
 * @return string|null
 */
function installed_version( string $file, string $pattern ): ?string {
	if ( ! is_file( $file ) || ! preg_match( $pattern, (string) file_get_contents( $file ), $matches ) ) {
		return null;
	}

	return $matches[1];
}

/**
 * Download a file to a temporary path.
 *
 * @param string $url
 *
 * @return string The temporary file, named after the URL so the archive type can be told.
 */
function download( string $url ): string {
	$context = stream_context_create(
		array(
			'http' => array(
				'follow_location' => 1,
				'timeout'         => 300,
				'user_agent'      => 'out-the-door-tests',
			),
		)
	);

	$source = @fopen( $url, 'rb', false, $context ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	if ( ! $source ) {
		fail( "Could not download {$url}." );
	}

	$target = sys_get_temp_dir() . '/' . uniqid( 'wpo-otd-' ) . '-' . basename( (string) parse_url( $url, PHP_URL_PATH ) );
	$sink   = fopen( $target, 'wb' );

	stream_copy_to_stream( $source, $sink );
	fclose( $source );
	fclose( $sink );

	return $target;
}

/**
 * Extract a .zip archive, then delete it.
 *
 * @param string $archive
 * @param string $destination
 *
 * @return void
 */
function extract_archive( string $archive, string $destination ): void {
	if ( ! is_dir( $destination ) ) {
		mkdir( $destination, 0777, true );
	}

	if ( str_ends_with( $archive, '.zip' ) && class_exists( ZipArchive::class ) ) {
		$zip = new ZipArchive();

		if ( true !== $zip->open( $archive ) || ! $zip->extractTo( $destination ) ) {
			fail( "Could not extract {$archive}." );
		}

		$zip->close();
	} else {
		// PharData reads zip archives without the zip extension.
		( new PharData( $archive ) )->extractTo( $destination, null, true );
	}

	unlink( $archive );
}

/**
 * Delete a directory and everything in it, if it exists.
 *
 * @param string $dir
 *
 * @return void
 */
function remove_directory( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		$item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}

	rmdir( $dir );
}

/**
 * Stop with an error.
 *
 * @param string $message
 *
 * @return never
 */
function fail( string $message ): never {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
}
