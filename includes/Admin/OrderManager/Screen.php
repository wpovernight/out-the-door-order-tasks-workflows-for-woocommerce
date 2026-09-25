<?php

namespace WPO\OTD\Admin\OrderManager;

use WPO\OTD\Admin\Assets;
use WPO\OTD\Services\TaskStatusRoleService;

defined( 'ABSPATH' ) || exit;

final class Screen {
	private readonly TaskStatusRoleService $task_status_role_service;

	/**
	 * Constructor.
	 *
	 * @param TaskStatusRoleService $task_status_role_service
	 */
	public function __construct( TaskStatusRoleService $task_status_role_service ) {
		$this->task_status_role_service = $task_status_role_service;
	}

	/**
	 * Register screen and actions.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
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

		if ( ! $screen || 'woocommerce_page_wpo_otd_order_manager' !== $screen->id ) {
			return;
		}

		Assets::register_runtime();
		/**
		 * Filters the script dependencies of the Order Manager app.
		 *
		 * @param string[] $deps Script handles the app depends on.
		 */
		$dependencies = apply_filters( 'wpo_otd_order_manager_script_deps', Assets::runtime_dependencies( true ) );

		// Do not need the suffix, since it's a React app and we are using webpack to handle the minification.
		wp_enqueue_script(
			'wpo-aom-admin-order-manager',
			WPO_OTD()->plugin_url() . '/assets/js/order-manager.js',
			$dependencies,
			WPO_OTD_VERSION,
			true
		);

		wp_set_script_translations(
			'wpo-aom-admin-order-manager',
			'out-the-door-order-tasks-workflows-for-woocommerce',
			WPO_OTD()->plugin_path() . '/languages'
		);

		wp_localize_script(
			'wpo-aom-admin-order-manager',
			'WPO_AOM_OrderManager',
			array(
				'apiRoot'      => esc_url_raw( rest_url( '/wc/v3' ) ),
				'apiNamespace' => 'wpo/otd',
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'statusRoles'  => array(
					'done'   => $this->task_status_role_service->get_done_field_option_id(),
					'undone' => $this->task_status_role_service->get_undone_field_option_id(),
				),
			)
		);

		/**
		 * Enqueue styles.
		 */
		wp_enqueue_style(
			'wpo-aom-admin-common',
			WPO_OTD()->plugin_url() . '/assets/css/common' . $suffix . '.css',
			array(),
			WPO_OTD_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-order-manager',
			WPO_OTD()->plugin_url() . '/assets/css/order-manager' . $suffix . '.css',
			array(),
			WPO_OTD_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-task-card',
			WPO_OTD()->plugin_url() . '/assets/css/task-card' . $suffix . '.css',
			array(),
			WPO_OTD_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-sidebar-modal',
			WPO_OTD()->plugin_url() . '/assets/css/sidebar-modal' . $suffix . '.css',
			array(),
			WPO_OTD_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-dashboard',
			WPO_OTD()->plugin_url() . '/assets/css/dashboard' . $suffix . '.css',
			array(),
			WPO_OTD_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-task-manager',
			WPO_OTD()->plugin_url() . '/assets/css/task-manager' . $suffix . '.css',
			array(),
			WPO_OTD_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-custom-order-status',
			WPO_OTD()->plugin_url() . '/assets/css/custom-order-status' . $suffix . '.css',
			array(),
			WPO_OTD_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-skeleton',
			WPO_OTD()->plugin_url() . '/assets/css/skeleton' . $suffix . '.css',
			array(),
			WPO_OTD_VERSION
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
			esc_html__( 'Order Manager', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
			esc_html__( 'Order Manager', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
			'manage_woocommerce',
			'wpo_otd_order_manager',
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
