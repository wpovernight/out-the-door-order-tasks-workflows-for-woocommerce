<?php

namespace WPO\OTD\Admin;

defined( 'ABSPATH' ) || exit;

final class Assets {
	public const SDK_HANDLE    = 'wpo-otd-sdk';
	public const ROUTER_HANDLE = 'wpo-otd-router';
	private const WP_DEPS      = array( 'wp-hooks', 'wp-element', 'wp-components', 'wp-i18n' );

	/**
	 * Register the runtime handles.
	 *
	 * @return void
	 */
	public static function register_runtime(): void {
		if ( wp_script_is( self::SDK_HANDLE, 'registered' ) ) {
			return;
		}

		wp_register_script(
			self::ROUTER_HANDLE,
			WPO_OTD()->plugin_url() . '/assets/js/router.js',
			array( 'wp-element' ),
			WPO_OTD_VERSION,
			true
		);

		wp_register_script(
			self::SDK_HANDLE,
			WPO_OTD()->plugin_url() . '/assets/js/sdk.js',
			self::WP_DEPS,
			WPO_OTD_VERSION,
			true
		);

		// The SDK components carry their own strings, so the SDK needs its own translations.
		wp_set_script_translations(
			self::SDK_HANDLE,
			'out-the-door-order-tasks-workflows-for-woocommerce',
			WPO_OTD()->plugin_path() . '/languages'
		);
	}

	/**
	 * Enqueue the plugin's stylesheets, minified unless SCRIPT_DEBUG is on.
	 *
	 * @param array<string, string> $styles Handle => file name in assets/css, without the extension.
	 *
	 * @return void
	 */
	public static function enqueue_styles( array $styles ): void {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		foreach ( $styles as $handle => $file ) {
			wp_enqueue_style(
				$handle,
				WPO_OTD()->plugin_url() . '/assets/css/' . $file . $suffix . '.css',
				array(),
				WPO_OTD_VERSION
			);
		}
	}

	/**
	 * Dependency list for a bundle that consumes the SDK runtime.
	 *
	 * @param bool $with_router
	 *
	 * @return string[]
	 */
	public static function runtime_dependencies( bool $with_router = false ): array {
		$deps = array_merge( self::WP_DEPS, array( self::SDK_HANDLE ) );

		if ( $with_router ) {
			$deps[] = self::ROUTER_HANDLE;
		}

		return $deps;
	}
}
