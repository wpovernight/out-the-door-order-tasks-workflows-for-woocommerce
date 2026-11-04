<?php

namespace WPO\OTD\Admin\OrderManager;

use WPO\OTD\Admin\Assets;
use WPO\OTD\Services\TaskStatusRoleService;

defined( 'ABSPATH' ) || exit;

final class Screen {
	/**
	 * The admin page's slug, under WooCommerce's menu.
	 */
	public const PAGE_SLUG = 'wpo_otd_order_manager';

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
	 * Hook the admin page and its assets.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_menu', array( $this, 'add_screen' ), 99 );
	}

	/**
	 * Enqueue the page's scripts and styles, on its own screen only.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$screen = get_current_screen();

		if ( ! $screen || 'woocommerce_page_' . self::PAGE_SLUG !== $screen->id ) {
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
			'wpo-otd-admin-order-manager',
			WPO_OTD()->plugin_url() . '/assets/js/order-manager.js',
			$dependencies,
			WPO_OTD_VERSION,
			true
		);

		wp_set_script_translations(
			'wpo-otd-admin-order-manager',
			'out-the-door-order-tasks-workflows-for-woocommerce',
			WPO_OTD()->plugin_path() . '/languages'
		);

		wp_localize_script(
			'wpo-otd-admin-order-manager',
			'WPO_OTD_OrderManager',
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

		Assets::enqueue_styles(
			array(
				'wpo-otd-admin-common'              => 'common',
				'wpo-otd-admin-order-manager'       => 'order-manager',
				'wpo-otd-admin-task-card'           => 'task-card',
				'wpo-otd-admin-sidebar-modal'       => 'sidebar-modal',
				'wpo-otd-admin-dashboard'           => 'dashboard',
				'wpo-otd-admin-task-manager'        => 'task-manager',
				'wpo-otd-admin-custom-order-status' => 'custom-order-status',
				'wpo-otd-admin-skeleton'            => 'skeleton',
			)
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
			esc_html__( 'Out the Door', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
			esc_html__( 'Out the Door', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * The admin URL of the task board: the page's kanban view.
	 *
	 * @return string
	 */
	public static function task_board_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG . '#/task-manager/kanban' );
	}

	/**
	 * The admin URL of the task archive: the page's archive view.
	 *
	 * @return string
	 */
	public static function task_archive_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG . '#/task-manager/archive' );
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

		// The React app renders into this root.
		echo '<div id="wpo-otd-order-manager"></div>';
	}
}
