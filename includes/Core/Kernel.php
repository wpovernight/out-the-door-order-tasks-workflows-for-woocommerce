<?php

namespace WPO\OTD\Core;

use WPO\OTD\Core\Container\Container;
use WPO\OTD\Core\Container\ServiceProvider;

defined( 'ABSPATH' ) || exit;

final class Kernel {
	/**
	 * Registered service providers.
	 *
	 * @var ServiceProvider[]
	 */
	private array $providers = array();

	/**
	 * Whether registering all providers has completed.
	 *
	 * @var bool
	 */
	private bool $registered = false;

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

		foreach ( $this->providers as $provider ) {
			$provider->register( $this->container );
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

		// Every binding exists by now, so provider order does not affect resolution.
		foreach ( $this->providers as $provider ) {
			$provider->boot( $this->container );
		}

		$this->booted = true;
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
