<?php

// ToDo: Update this class whenever the final design of the custom status feature is finalized.

namespace WPO\AOM\Admin\CustomOrderStatus;

defined( 'ABSPATH' ) || exit;

final class Screen {

	/**
	 * Register settings tab and actions.
	 *
	 * @return void
	 */
	public function register(): void {
		// Add a new settings tab for custom order status.
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_wpo_aom_custom_status_tab', array( $this, 'render_tab_content' ) );

		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add a new settings tab for custom order status.
	 *
	 * @param array $pages
	 *
	 * @return array
	 */
	public static function add_settings_tab( array $pages ): array {
		$pages['wpo_aom_custom_status_tab'] = __( 'Custom Status', 'wpo-aom' );

		return $pages;
	}

	/**
	 * Render the content of the settings tab.
	 *
	 * @return void
	 */
	public function render_tab_content() {
		global $hide_save_button;
		$hide_save_button = true;

		$table = new \WPO\AOM\Admin\CustomOrderStatus\Table();
		$table->display_tab_content();
	}

	public function enqueue_scripts(): void {
		$screen = get_current_screen();

		if (
			! $screen ||
			'woocommerce_page_wc-settings' !== $screen->id ||
			! isset( $_GET['tab'] ) ||
			'wpo_aom_custom_status_tab' !== $_GET['tab']
		) {
			return;
		}

		wp_enqueue_style(
			'wpo-aom-custom-status',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/custom-order-status.css',
			array(),
			WPO_AOM_VERSION
		);
	}

}
