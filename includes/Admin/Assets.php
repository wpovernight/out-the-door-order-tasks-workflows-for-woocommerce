<?php

namespace WPO\AOM\Admin;

defined( 'ABSPATH' ) || exit;

final class Assets {
	public const SDK_HANDLE    = 'wpo-aom-sdk';
	public const ROUTER_HANDLE = 'wpo-aom-router';
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
			WPO_AOM()->plugin_url() . '/assets/js/router.js',
			array( 'wp-element' ),
			WPO_AOM_VERSION,
			true
		);

		wp_register_script(
			self::SDK_HANDLE,
			WPO_AOM()->plugin_url() . '/assets/js/sdk.js',
			self::WP_DEPS,
			WPO_AOM_VERSION,
			true
		);

		// The SDK components carry their own translatable strings now that they
		// are no longer inlined into the consuming bundles.
		wp_set_script_translations(
			self::SDK_HANDLE,
			'advanced-order-manager',
			WPO_AOM()->plugin_path() . '/languages'
		);
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
