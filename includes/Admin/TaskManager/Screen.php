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
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/js/task-manager.js',
			array( 'wp-element', 'wp-components', 'wp-i18n' ),
			WPO_AOM_VERSION,
			true
		);

		wp_set_script_translations( 'wpo-aom-admin-task-manager', 'wpo-aom', WPO_AOM()->plugin_path() . '/languages' );

		wp_localize_script(
			'wpo-aom-admin-task-manager',
			'WPO_AOM_TaskManager',
			array(
				'apiRoot'      => esc_url_raw( rest_url( '/wc/v3' ) ),
				'apiNamespace' => 'wpo/aom',
				'nonce'        => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_enqueue_style(
			'wpo-aom-admin-common',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/common.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-task-card',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/task-card.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-sidebar-modal',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/sidebar-modal.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-task-manager',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/task-manager.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-skeleton',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/skeleton.css',
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
