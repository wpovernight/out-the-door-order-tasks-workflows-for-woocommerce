<?php

namespace WPO\AOM;

use WPO\AOM\Core\Install;
use WPO\AOM\Core\DependencyChecker;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WPO\AOM\Admin\CustomOrderStatusAdmin;
use WPO\AOM\Services\CustomOrderStatusService;

defined( 'ABSPATH' ) || exit;

final class AdvancedOrderManager {

	public const VERSION = '1.0.0';

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
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'wpo_aom' ), '1.0.0' );
	}

	/**
	 * Prevent unserialization.
	 *
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing is forbidden.', 'wpo_aom' ), '1.0.0' );
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
		add_action( 'init', array( $this, 'translations' ), 9 );
		add_action( 'init', array( $this, 'initialize' ) );

		// Declare Woo features compatibility.
		add_action( 'before_woocommerce_init', array( $this, 'woo_features_compatibility' ) );
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function translations(): void {
		$text_domain             = 'wpo_aom';
		$locale                  = apply_filters( 'plugin_locale', determine_locale(), $text_domain );
		$custom_translation_path = WP_LANG_DIR . '/wpo-advanced-order-manager/wpo-advanced-order-manager-' . $locale . '.mo';
		$plugin_translation_path = WP_LANG_DIR . '/plugins/wpo-advanced-order-manager-' . $locale . '.mo';

		unload_textdomain( $text_domain );
		load_textdomain( $text_domain, $custom_translation_path );
		load_textdomain( $text_domain, $plugin_translation_path );
		load_plugin_textdomain( $text_domain, false, dirname( plugin_basename( WPO_AOM_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Initialize the plugin and register services.
	 *
	 * @return void
	 */
	public function initialize(): void {
		$this->register_services();
	}

	/**
	 * Return the map of service properties to class names.
	 *
	 * @return array<string,class-string>
	 */
	private function service_map(): array {
		$map = array(
			'custom_order_status'       => array( CustomOrderStatusService::class, true ),
			'custom_order_status_admin' => array( CustomOrderStatusAdmin::class, false ),
		);

		/**
		 * Filters the Advanced Order Manager service map.
		 *
		 * @param array<string,array> $map Service map.
		 */
		return (array) apply_filters( 'wpo_aom_service_map', $map );
	}

	/**
	 * Instantiate and store services (singleton- or constructor-based).
	 *
	 * @return void
	 */
	private function register_services(): void {
		foreach ( $this->service_map() as $property => $definition ) {
			[ $class, $store ] = $definition;

			$service = $this->resolve_service( $class );

			if ( method_exists( $service, 'register' ) ) {
				$service->register();
			}

			// Store the service in a dynamic property if specified.
			if ( $store && ! property_exists( $this, $property ) ) {
				/* @phpstan-ignore-next-line Suppressing type warning for dynamic property assignment. */
				$this->{$property} = $service;
			}
		}
	}

	/**
	 * Resolve a service instance (supports overrides, ::instance(), or new).
	 *
	 * @param string $class
	 *
	 * @return object
	 */
	private function resolve_service( string $class ): object {
		if ( is_callable( array( $class, 'instance' ) ) ) {
			return $class::instance();
		}

		return new $class();
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

}
