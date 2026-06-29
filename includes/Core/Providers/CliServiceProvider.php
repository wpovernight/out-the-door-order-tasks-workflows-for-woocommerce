<?php

namespace WPO\AOM\Core\Providers;

use WP_CLI;
use WPO\AOM\Core\Container\Container;
use WPO\AOM\Core\Container\ServiceProvider;

defined( 'ABSPATH' ) || exit;

final class CliServiceProvider implements ServiceProvider {
	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
	}
}
