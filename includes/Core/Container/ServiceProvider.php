<?php
/**
 * Service provider contract.
 *
 * A provider is how a module contributes to the application.
 * The two phases are:
 *
 *   register() — bind definitions only. No WordPress hooks, no side effects.
 *                Runs for ALL providers before any boot() runs, so resolution
 *                never depends on provider order.
 *
 *   boot()     — wire into WordPress (hooks, REST routes, admin screens).
 *                Safe to resolve any service here because every binding exists.
 *
 */

namespace WPO\OTD\Core\Container;

defined( 'ABSPATH' ) || exit;

interface ServiceProvider {
	/**
	 * Bind definitions into the container. No side effects.
	 *
	 * @param Container $container Application container.
	 *
	 * @return void
	 */
	public function register( Container $container ): void;

	/**
	 * Wire the module into WordPress. Runs after all providers have registered.
	 *
	 * @param Container $container Application container.
	 *
	 * @return void
	 */
	public function boot( Container $container ): void;
}
