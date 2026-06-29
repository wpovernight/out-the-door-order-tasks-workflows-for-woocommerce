<?php
/**
 * Marks a service provider as deferred.
 *
 * A deferred provider is NOT registered at boot. Instead, the Kernel records the
 * ids it promises (via provides()) and only calls the provider's register()
 * the first time one of those ids is resolved from the container. This keeps
 * heavy, rarely-used services out of the hot path on every request.
 *
 * Deferred providers should be pure-binding where possible. If one also needs a
 * boot() side effect, the Kernel runs it immediately after the lazy register(),
 * provided the eager boot phase has started (which it has by the time any id is
 * resolved on a normal request).
 */

namespace WPO\AOM\Core\Container;

defined( 'ABSPATH' ) || exit;

interface DeferrableProvider extends ServiceProvider {
	/**
	 * The service ids this provider binds.
	 *
	 * Resolving any of these from the container triggers a one-time lazy
	 * registration of the provider.
	 *
	 * Example:
	 * public function provides(): array {
	 *     return array( ReportExporter::class );
	 * }
	 *
	 * The ids returned here must match what register() binds. The first
	 * $container->get( ReportExporter::class ) runs this provider's register().
	 *
	 * @return string[] List of fully-qualified class/interface names.
	 */
	public function provides(): array;
}
