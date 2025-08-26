<?php

namespace WPO\AOM\Admin\CustomOrderStatus;

use WPO\AOM\Models\CustomOrderStatus;

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

		// Add a new settings tab for custom order status.
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_wpo_aom_custom_status_tab', array( $this, 'render_tab_content' ) );
		add_action( 'woocommerce_update_options_wpo_aom_custom_status_tab', array( $this, 'save_tab_content' ) );

		// Custom field for woocommerce_admin_fields() to display the preview.
		add_action( 'woocommerce_admin_field_wpo_aom_cos_preview', array( $this, 'render_preview_field' ) );
	}

	/**
	 * Enqueue admin scripts and styles for the custom order status tab.
	 *
	 * @return void
	 */
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

		if ( 'edit' === $this->get_current_action() ) {
			wp_enqueue_script(
				'wpo-aom-custom-status-script',
				WPO_AOM()->plugin_url() . '/includes/Admin/assets/js/custom-order-status.js',
				array( 'jquery' ),
				WPO_AOM_VERSION,
				true
			);
		}
	}

	/**
	 * Add a new settings tab for custom order status.
	 *
	 * @param array $pages
	 *
	 * @return array
	 */
	public static function add_settings_tab( array $pages ): array {
		$pages['wpo_aom_custom_status_tab'] = __( 'Custom Order Status', 'wpo-aom' );

		return $pages;
	}

	/**
	 * Render the content of the settings tab.
	 *
	 * @return void
	 */
	public function render_tab_content() {
		if ( $this->get_current_action() === 'edit' ) {
			$this->render_edit_screen();
		} else {
			$this->render_list_table();
		}
	}

	/**
	 * Render the list table of custom order statuses.
	 *
	 * @return void
	 */
	private function render_list_table(): void {
		global $hide_save_button;
		$hide_save_button = true;

		$table = new Table();
		$table->display_tab_content();
	}

	/**
	 * Render the edit screen for a custom order status.
	 *
	 * @return void
	 */
	private function render_edit_screen(): void {
		if (
			empty( $_GET['status_id'] ) ||
			! is_numeric( $_GET['status_id'] ) ||
			! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'wpo_aom_edit_custom_order_status' )
		) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Invalid request.', 'wpo-aom' )
			);

			return;
		}

		$status_id = absint( $_GET['status_id'] );
		$status    = WPO_AOM()->custom_order_status->find( $status_id );

		if ( ! $status ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Custom order status not found.', 'wpo-aom' )
			);

			return;
		}

		// Display the edit form.
		woocommerce_admin_fields( $this->get_edit_settings_fields( $status ) );
	}

	/**
	 * Get the settings fields for editing a custom order status.
	 *
	 * @param CustomOrderStatus|null $status
	 *
	 * @return array
	 */
	private function get_edit_settings_fields( CustomOrderStatus $status = null ): array {
		$option_name = 'wpo_aom_custom_order_status_options';

		return array(
			array(
				'title' => esc_html__( 'Edit Custom Order Status', 'wpo-aom' ),
				'type'  => 'title',
				'id'    => $option_name,
			),
			array(
				'title'    => esc_html__( 'Label', 'wpo-aom' ),
				'id'       => 'wpo_aom_custom_order_status_label',
				'type'     => 'text',
				'desc'     => esc_html__( 'The label for the custom order status.', 'wpo-aom' ),
				'default'  => $status ? $status->label : '',
				'required' => true,
			),
			array(
				'title'             => esc_html__( 'Status key', 'wpo-aom' ),
				'id'                => 'wpo_aom_custom_order_status_key',
				'type'              => 'text',
				'desc'              => esc_html__( 'The unique key for the custom order status. Only lowercase letters, numbers, and underscores are allowed.', 'wpo-aom' ),
				'default'           => $status ? $status->status_key : '',
				'required'          => true,
				'custom_attributes' => array(
					'pattern' => '^[a-z0-9_]+$',
					'title'   => esc_html__( 'Only lowercase letters, numbers, and underscores are allowed.', 'wpo-aom' ),
				),
			),
			array(
				'title'    => esc_html__( 'Background', 'wpo-aom' ),
				'id'       => 'wpo_aom_custom_order_status_background',
				'type'     => 'color',
				'desc'     => esc_html__( 'The background color for the custom order status.', 'wpo-aom' ),
				'default'  => $status ? $status->background : '#ccc',
				'required' => true,
			),
			array(
				'title' => esc_html__( 'Preview', 'wpo-aom' ),
				'id'    => 'wpo_aom_custom_order_status_preview',
				'type'  => 'wpo_aom_cos_preview',
			),
			array(
				'type' => 'sectionend',
				'id'   => $option_name,
			),
		);
	}

	/**
	 * Render the custom preview field for the edit page.
	 *
	 * @param array $value
	 *
	 * @return void
	 */
	public function render_preview_field( array $value ): void {
		printf(
			'<tr class="%s">
				<th scope="row" class="titledesc">
					<label>%s</label>
				</th>
				<td class="forminp forminp-%s">
					<span class="wpo-aom-custom-order-status-preview order-status" id="%s"></span>
				</td>
			</tr>',
			esc_attr( $value['row_class'] ),
			esc_html( $value['title'] ),
			esc_attr( sanitize_title( $value['type'] ) ),
			esc_attr( $value['id'] )
		);
	}

	/**
	 * Save the settings from the custom order status tab.
	 *
	 * @return void
	 */
	public function save_tab_content(): void {
		if ( 'edit' === $this->get_current_action() ) {
			$status_id = absint( $_GET['status_id'] ?? 0 );
			$data      = array(
				'status_key' => sanitize_text_field( $_POST['wpo_aom_custom_order_status_key'] ?? '' ),
				'label'      => sanitize_text_field( $_POST['wpo_aom_custom_order_status_label'] ?? '' ),
				'background' => sanitize_hex_color( $_POST['wpo_aom_custom_order_status_background'] ?? '' ),
			);

			WPO_AOM()->custom_order_status->update( $status_id, $data );
		}
	}

	/**
	 * Get the current action from the request.
	 *
	 * @return string
	 */
	private function get_current_action(): string {
		$action = $_REQUEST['action'] ?? '';

		// Only allow specific actions.
		if ( ! in_array( $action, array( 'edit' ), true ) ) {
			$action = '';
		}

		return $action;
	}

}
