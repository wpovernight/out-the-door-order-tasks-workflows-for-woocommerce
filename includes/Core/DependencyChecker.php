<?php

namespace WPO\OTD\Core;

use WPO\OTD\Admin\Notices\MissingDependenciesNotice;

defined( 'ABSPATH' ) || exit;

final class DependencyChecker {
	private const PHP_MIN_VERSION = '8.1';
	private const WC_MIN_VERSION  = '8.2';

	protected static ?self $_instance = null;

	/**
	 * Get the instance of the class.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Check if all dependencies are met.
	 *
	 * @return bool
	 */
	public function check_dependencies(): bool {
		$errors = $this->get_errors();

		if ( ! empty( $errors ) ) {
			( new MissingDependenciesNotice( $errors ) )->register_hooks();

			return false;
		}

		return true;
	}

	/**
	 * Get an array of error messages if the plugin is not compatible with the current environment.
	 *
	 * @return string[]
	 */
	private function get_errors(): array {
		$errors = array();

		if ( ! $this->is_php_version_compatible() ) {
			$errors[] = sprintf(
				/* translators: %s: minimum PHP version */
				__( 'PHP %s+ is required.', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
				self::PHP_MIN_VERSION
			);
		}

		if ( ! $this->is_wc_activated() ) {
			$errors[] = __( 'WooCommerce must be activated.', 'out-the-door-order-tasks-workflows-for-woocommerce' );
		} elseif ( ! $this->is_wc_version_compatible() ) {
			$errors[] = sprintf(
				/* translators: %s: minimum WooCommerce version */
				__( 'WooCommerce %s+ is required.', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
				self::WC_MIN_VERSION
			);
		}

		return $errors;
	}

	/**
	 * Check if the required PHP version is met.
	 *
	 * @return bool
	 */
	private function is_php_version_compatible(): bool {
		return version_compare( PHP_VERSION, self::PHP_MIN_VERSION, '>=' );
	}

	/**
	 * Check if WooCommerce is activated.
	 *
	 * @return bool
	 */
	private function is_wc_activated(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Check if the WooCommerce version is compatible.
	 *
	 * @return bool
	 */
	private function is_wc_version_compatible(): bool {
		return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::WC_MIN_VERSION, '>=' );
	}
}
