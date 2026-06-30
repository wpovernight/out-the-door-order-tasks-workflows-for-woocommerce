<?php

namespace WPO\AOM\Core;

use WPO\AOM\Core\Container\Container;
use WPO\AOM\Core\Container\DeferrableProvider;
use WPO\AOM\Core\Container\ServiceProvider;

defined( 'ABSPATH' ) || exit;

final class Kernel {
	/**
	 * Registered service providers.
	 *
	 * @var ServiceProvider[]
	 */
	private array $providers = array();

	/**
	 * Eagerly loaded service providers.
	 *
	 * @var ServiceProvider[]
	 */
	private array $eager = array();

	/**
	 * Deferred providers already lazily loaded.
	 *
	 * @var array<int, bool>
	 */
	private array $loaded_deferred = array();

	/**
	 * Whether registering all providers has completed.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Whether the eager boot phase has started.
	 *
	 * @var bool
	 */
	private bool $booting = false;

	/**
	 * Whether boot() has already run to completion (guards against double-boot).
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Application container.
	 *
	 * @var Container
	 */
	private readonly Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container Application container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Add a provider. Must be called before register()/boot().
	 *
	 * @param ServiceProvider $provider Module provider.
	 *
	 * @return void
	 */
	public function add_provider( ServiceProvider $provider ): void {
		$this->providers[] = $provider;
	}

	/**
	 * Register every provider's bindings. No boot, no side effects.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->registered ) {
			return;
		}

		// Register eager providers and record deferred ones for lazy load.
		foreach ( $this->providers as $provider ) {
			if ( $provider instanceof DeferrableProvider ) {
				$this->register_deferred( $provider );
			} else {
				$provider->register( $this->container );
				$this->eager[] = $provider;
			}
		}

		$this->registered = true;
	}

	/**
	 * Boot the kernel.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->register();

		// Boot eager providers. Every eager binding exists by now, so provider
		// order does not affect resolution. Flag the phase as started first, so
		// a deferred id resolved mid-boot also gets booted on load.
		$this->booting = true;

		foreach ( $this->eager as $provider ) {
			$provider->boot( $this->container );
		}

		$this->booted = true;
	}

	/**
	 * Map a deferred provider's promised ids to a lazy loader in the container.
	 *
	 * @param DeferrableProvider $provider Deferred provider.
	 *
	 * @return void
	 */
	private function register_deferred( DeferrableProvider $provider ): void {
		foreach ( $provider->provides() as $id ) {
			// Only record a lazy loader against each id the provider promises.
			// The provider's bindings stay unregistered for now. When the container
			// is first asked for one of these ids, it runs the loader, which calls
			// load_deferred_provider() to register the provider, and only then
			// resolves the requested service.
			$this->container->defer(
				$id,
				function () use ( $provider ) {
					$this->load_deferred_provider( $provider );
				}
			);
		}
	}

	/**
	 * Register and boot a deferred provider once.
	 *
	 * @param DeferrableProvider $provider Deferred provider being resolved.
	 *
	 * @return void
	 */
	private function load_deferred_provider( DeferrableProvider $provider ): void {
		$key = spl_object_id( $provider );

		if ( isset( $this->loaded_deferred[ $key ] ) ) {
			return;
		}

		$this->loaded_deferred[ $key ] = true;

		// Forget all of this provider's promised ids so resolving any of them won't
		// trigger this lazy registration again.
		$this->container->forget_deferred( $provider->provides() );

		$provider->register( $this->container );

		// A lazily loaded provider arrives after the eager boot loop, so boot it here.
		// $booting is false only during register-only activation, where nothing boots.
		if ( $this->booting ) {
			$provider->boot( $this->container );
		}
	}

	/**
	 * Expose the container for composition-root use only (e.g. bootstrap).
	 *
	 * NOTE: NOT a service-locator seam: domain code must receive dependencies via
	 * constructor injection, never reach the container through this.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}
}
