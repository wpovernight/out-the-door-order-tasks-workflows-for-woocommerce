<?php

namespace WPO\AOM;

defined( 'ABSPATH' ) || exit;

final class DependencyChecker {

	private const PHP_MIN_VERSION = '7.4';
	private const WC_MIN_VERSION  = '8.2';

	private array $activated_plugins;

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
	private function __construct() {
		$this->activated_plugins = array_merge(
			(array) get_option( 'active_plugins', array() ),
			(array) get_site_option( 'active_sitewide_plugins', array() )
		);
	}

	/**
	 * Check if all dependencies are met.
	 *
	 * @return bool
	 */
	public function check_dependencies(): bool {
		$errors = $this->get_errors();

		if ( ! empty( $errors ) ) {
			add_action( 'admin_notices', function () use ( $errors ) {
				$this->display_admin_notice( $errors );
			} );

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
				__( 'PHP %s+ is required.', 'wpo_aom' ),
				self::PHP_MIN_VERSION
			);
		}

		if ( ! $this->is_wc_activated() ) {
			$errors[] = __( 'WooCommerce must be activated.', 'wpo-aom' );
		} elseif ( ! $this->is_wc_version_compatible() ) {
			$errors[] = sprintf(
				/* translators: %s: minimum WooCommerce version */
				__( 'WooCommerce %s+ is required.', 'wpo_aom' ),
				self::WC_MIN_VERSION
			);
		}

		return $errors;
	}

	/**
	 * Check if the plugin is active.
	 *
	 * @param string $plugin_slug
	 *
	 * @return bool
	 */
	private function is_active( string $plugin_slug ): bool {
		return in_array( $plugin_slug, $this->activated_plugins, true ) ||
		       array_key_exists( $plugin_slug, $this->activated_plugins, true );
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
		return $this->is_active( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Check if the WooCommerce version is compatible.
	 *
	 * @return bool
	 */
	private function is_wc_version_compatible(): bool {
		return defined( 'WC_VERSION' ) &&
		       version_compare( WC_VERSION, self::WC_MIN_VERSION, '>=' );
	}

	/**
	 * Display an admin notice if the plugin is not compatible with the current environment.
	 *
	 * @param string[] $errors
	 *
	 * @return void
	 */
	public function display_admin_notice( array $errors ): void {
		$title   = '<strong>' . esc_html__( 'Advanced Order Manager for WooCommerce', 'wpo_aom' ) . '</strong>';
		$content = esc_html__( 'can’t run because:', 'wpo-aom' );
		$list    = '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $errors ) ) . '</li></ul>';

		printf(
			'<div class="notice notice-error"><p>%1$s %2$s</p>%3$s</div>',
			wp_kses_post( $title ),
			esc_html( $content ),
			wp_kses_post( $list )
		);
	}

}
