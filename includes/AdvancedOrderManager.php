<?php

namespace WPO\AOM;

use InvalidArgumentException;
use WPO\AOM\Core\Install;
use WPO\AOM\Core\DependencyChecker;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WPO\AOM\Core\ServiceContainer;

defined( 'ABSPATH' ) || exit;

final class AdvancedOrderManager {

	public const VERSION = '1.0.0';
	public ServiceContainer $service_container;

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
	 * Prevent cloning.
	 *
	 * @return void
	 */
	private function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'wpo-advanced-order-manager' ), '1.0.0' );
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		require $this->plugin_path() . '/vendor/autoload.php';

		// Check dependencies.
		$dependencies = DependencyChecker::instance();
		if ( ! $dependencies->check_dependencies() ) {
			return;
		}

		// Load the Install class.
		Install::instance()->register();

		$this->define_constants();
		$this->init_hooks();

		// Register services and repositories.
		$this->service_container = new ServiceContainer();
		$this->service_container->register();
	}

	/**
	 * Define plugin constants.
	 *
	 * @return void
	 */
	private function define_constants(): void {
		$this->define( 'WPO_AOM_VERSION', self::VERSION );
	}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', array( $this, 'translations' ) );

		// Declare Woo features compatibility.
		add_action( 'before_woocommerce_init', array( $this, 'woo_features_compatibility' ) );
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function translations(): void {
		$text_domain             = 'wpo-advanced-order-manager';
		$locale                  = apply_filters( 'plugin_locale', determine_locale(), $text_domain );
		$custom_translation_path = WP_LANG_DIR . '/wpo-advanced-order-manager/wpo-advanced-order-manager-' . $locale . '.mo';
		$plugin_translation_path = WP_LANG_DIR . '/plugins/wpo-advanced-order-manager-' . $locale . '.mo';

		unload_textdomain( $text_domain );
		load_textdomain( $text_domain, $custom_translation_path );
		load_textdomain( $text_domain, $plugin_translation_path );
		load_plugin_textdomain( $text_domain, false, dirname( plugin_basename( WPO_AOM_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Declare WooCommerce features compatibility.
	 *
	 * @return void
	 */
	public function woo_features_compatibility(): void {
		if ( class_exists( FeaturesUtil::class ) ) {
			// HPOS (compatible)
			FeaturesUtil::declare_compatibility( 'custom_order_tables', WPO_AOM_PLUGIN_FILE, true );
		}
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string $name Constant name.
	 * @param bool|string $value Constant value.
	 *
	 * @return void
	 */
	private function define( string $name, $value ): void {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	public function plugin_url(): string {
		return untrailingslashit( plugins_url( '/', WPO_AOM_PLUGIN_FILE ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public function plugin_path(): string {
		return untrailingslashit( plugin_dir_path( WPO_AOM_PLUGIN_FILE ) );
	}

	/**
	 * Get a service instance from the service container.
	 *
	 * @param string $id Service ID.
	 *
	 * @return object
	 *
	 * @throws InvalidArgumentException If the service ID is not defined.
	 */
	public function get_service( string $id ): object {
		return $this->service_container->resolve_service( $id );
	}
}
