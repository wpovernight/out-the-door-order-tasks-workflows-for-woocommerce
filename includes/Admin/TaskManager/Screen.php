<?php

namespace WPO\AOM\Admin\TaskManager;

defined( 'ABSPATH' ) || exit;

final class Screen {
	/**
	 * Register settings tab and actions.
	 *
	 * @return void
	 */
	public function register(): void {
		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add a sub menu item under WooCommerce settings.
		add_action( 'admin_menu', array( $this, 'add_settings_tab' ), 99 );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$screen = get_current_screen();

		if ( ! $screen || 'woocommerce_page_wpo_aom_task_manager' !== $screen->id ) {
			return;
		}

		wp_enqueue_script(
			'wpo-aom-admin-task-manager',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/js/task-manager/build/task-manager.js',
			array( 'wp-element' ),
			WPO_AOM_VERSION,
			true
		);

		wp_localize_script(
			'wpo-aom-admin-task-manager',
			'WPO_AOM_TaskManager',
			array(
				'apiRoot'      => esc_url_raw( rest_url( '/wc/v3/wpo/aom' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'loading'      => esc_html__( 'Loading...', 'wpo-aom' ),
				'errorLoading' => esc_html__( 'Error loading tasks. Please try again.', 'wpo-aom' ),
				'views'        => array(
					'kanban'   => esc_html__( 'Kanban', 'wpo-aom' ),
					'calendar' => esc_html__( 'Calendar', 'wpo-aom' ),
				),
			)
		);

		wp_enqueue_style(
			'wpo-aom-admin-task-manager',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/task-manager.css',
			array(),
			WPO_AOM_VERSION
		);
	}

	/**
	 * Add settings tab under WooCommerce menu.
	 *
	 * @return void
	 */
	public function add_settings_tab(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Task Management', 'wpo-aom' ),
			__( 'Task Management', 'wpo-aom' ),
			'manage_woocommerce',
			'wpo_aom_task_manager',
			array( $this, 'render_settings_tab' )
		);
	}

	/**
	 * Render settings tab content.
	 *
	 * @return void
	 */
	public function render_settings_tab(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		echo '<div id="wpo-aom-task-management">
			<h1>', esc_html__( 'Task Management', 'wpo-aom' ), '</h1>
			<div id="wpo-aom-task-manager-container"></div>
			</div>';
	}
}
