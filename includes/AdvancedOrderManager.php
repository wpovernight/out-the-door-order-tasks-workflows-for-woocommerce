<?php

namespace WPO\AOM;

use WPO\AOM\Core\DependencyChecker;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WPO\AOM\Core\Kernel;
use WPO\AOM\Core\Container\Container;
use WPO\AOM\Core\Container\ServiceProvider;
use WPO\AOM\Core\Providers\CoreServiceProvider;
use WPO\AOM\Core\Providers\CliServiceProvider;

defined( 'ABSPATH' ) || exit;

final class AdvancedOrderManager {
	public const VERSION = '1.0.0';

	private ?Kernel $kernel = null;

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
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'advanced-order-manager' ), '1.0.0' );
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

		$this->define_constants();
		$this->init_hooks();

		// Build and boot the service kernel once all plugins have loaded.
		add_action( 'plugins_loaded', array( $this, 'boot_kernel' ), 11 );
	}

	/**
	 * Build the container/kernel, collect providers, and boot.
	 *
	 * @return void
	 */
	public function boot_kernel(): void {
		$this->kernel = new Kernel( new Container() );
		$this->kernel->add_provider( new CoreServiceProvider() );

		// Register the WP-CLI provider.
		// The class_exists() check should NOT be removed, as the CLI classes
		// are excluded from the distributed build.
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( CliServiceProvider::class ) ) {
			$this->kernel->add_provider( new CliServiceProvider() );
		}

		/**
		 * Filter the list of service providers to register with the kernel.
		 *
		 * @param ServiceProvider[] $providers
		 */
		$providers = (array) apply_filters( 'wpo_aom_service_providers', array() );

		foreach ( $providers as $provider ) {
			if ( $provider instanceof ServiceProvider ) {
				$this->kernel->add_provider( $provider );
			} else {
				_doing_it_wrong(
					__METHOD__,
					esc_html__( 'Each "wpo_aom_service_providers" entry must implement ServiceProvider.', 'advanced-order-manager' ),
					'1.0.0'
				);
			}
		}

		$this->kernel->boot();
	}

	/**
	 * Define plugin constants.
	 *
	 * @return void
	 */
	private function define_constants(): void {
		defined( 'WPO_AOM_VERSION' ) || define( 'WPO_AOM_VERSION', self::VERSION );
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
		$text_domain             = 'advanced-order-manager';
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- `plugin_locale` is a WordPress core filter that plugins apply when loading their own translations.
		$locale                  = apply_filters( 'plugin_locale', determine_locale(), $text_domain );
		$custom_translation_path = WP_LANG_DIR . '/advanced-order-manager/advanced-order-manager-' . $locale . '.mo';
		$plugin_translation_path = WP_LANG_DIR . '/plugins/advanced-order-manager-' . $locale . '.mo';

		unload_textdomain( $text_domain );
		load_textdomain( $text_domain, $custom_translation_path );
		load_textdomain( $text_domain, $plugin_translation_path );
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
