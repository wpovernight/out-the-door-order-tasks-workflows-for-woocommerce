<?php

// ToDo: Update this class whenever the final design of the custom status feature is finalized.

namespace WPO\AOM\Admin;

defined( 'ABSPATH' ) || exit;

final class CustomOrderStatusAdmin {

	/**
	 * Register settings tab and actions.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_wpo_aom_custom_status_tab', array( $this, 'render_tab_content' ) );
		add_action( 'woocommerce_update_options_wpo_aom_custom_status_tab', array( $this, 'save_settings' ) );
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

	// Temporary function to render the settings tab content.
	public function render_tab_content() {
		woocommerce_admin_fields( $this->get_settings() );
	}

	// Temporary function to save the settings.
	public function save_settings() {
		woocommerce_update_options( $this->get_settings() );
	}

	// Temporary function to get the settings for the custom status tab.
	private function get_settings(): array {
		return array(
			array(
				'title' => __( 'Custom Status Settings', 'wpo_aom' ),
				'type'  => 'title',
				'id'    => 'wpo_aom_custom_status_section',
			),
			array(
				'title'   => __( 'Enable Feature', 'wpo_aom' ),
				'type'    => 'checkbox',
				'id'      => 'wpo_aom_enable_custom_status',
				'default' => 'yes',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wpo_aom_custom_status_section',
			),
		);
	}

}
