<?php

namespace WPO\AOM\Admin\OrderManager;

defined( 'ABSPATH' ) || exit;

final class Screen {
	/**
	 * Register screen and actions.
	 *
	 * @return void
	 */
	public function register(): void {
		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add a sub menu item under WooCommerce menu.
		add_action( 'admin_menu', array( $this, 'add_screen' ), 99 );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$screen = get_current_screen();
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		if ( ! $screen || 'woocommerce_page_wpo_aom_order_manager' !== $screen->id ) {
			return;
		}

		wp_enqueue_script(
			'wpo-aom-admin-order-manager',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/js/order-manager.js',
			array( 'wp-element', 'wp-components', 'wp-i18n' ),
			WPO_AOM_VERSION,
			true
		);

		wp_set_script_translations( 'wpo-aom-admin-order-manager', 'wpo-aom', WPO_AOM()->plugin_path() . '/languages' );

		wp_localize_script(
			'wpo-aom-admin-order-manager',
			'WPO_AOM_OrderManager',
			array(
				'apiRoot'      => esc_url_raw( rest_url( '/wc/v3' ) ),
				'apiNamespace' => 'wpo/aom',
				'nonce'        => wp_create_nonce( 'wp_rest' ),
			)
		);

		/**
		 * Enqueue styles.
		 */
		wp_enqueue_style(
			'wpo-aom-admin-common',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/common' . $suffix . '.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-order-manager',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/order-manager' . $suffix . '.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-task-card',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/task-card' . $suffix . '.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-sidebar-modal',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/sidebar-modal' . $suffix . '.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-dashboard',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/dashboard' . $suffix . '.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-task-manager',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/task-manager' . $suffix . '.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-custom-order-status',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/custom-order-status' . $suffix . '.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-skeleton',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/skeleton' . $suffix . '.css',
			array(),
			WPO_AOM_VERSION
		);
	}

	/**
	 * Add the screen under WooCommerce menu.
	 *
	 * @return void
	 */
	public function add_screen(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Order Manager', 'wpo-aom' ),
			__( 'Order Manager', 'wpo-aom' ),
			'manage_woocommerce',
			'wpo_aom_order_manager',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the page content.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Handles using the React app.
		echo '<div id="wpo-aom-order-manager"></div>';
	}
}
