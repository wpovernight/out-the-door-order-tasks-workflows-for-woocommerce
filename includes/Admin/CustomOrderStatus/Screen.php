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

		// Delete a custom order status using AJAX.
		add_action( 'wp_ajax_wpo_aom_delete_custom_order_status', array( $this, 'ajax_delete_custom_order_status' ) );
	}

	/**
	 * Enqueue admin scripts and styles for the custom order status tab.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$screen = get_current_screen();
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

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
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/custom-order-status' . $suffix . '.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_script(
			'wpo-aom-custom-status-script',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/js/custom-order-status' . $suffix . '.js',
			array( 'jquery' ),
			WPO_AOM_VERSION,
			true
		);

		wp_localize_script(
			'wpo-aom-custom-status-script',
			'wpo_aom_cos_params',
			array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'wpo_aom_cos' ),
				'confirm_text' => esc_html__( 'Are you sure?', 'wpo-aom' ),
			)
		);
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
		switch ( $this->get_current_action() ) {
			case 'create':
				$this->render_create_screen();
				break;
			case 'edit':
				$this->render_edit_screen();
				break;
			default:
				$this->render_list_table();
				break;
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

		// Add title and "Add New" button.
		printf(
			'<h2>%s <a href="%s" class="page-title-action">%s</a></h2>',
			esc_html__( 'Custom Order Statuses', 'wpo-aom' ),
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=wpo_aom_custom_status_tab&action=create' ) ),
			esc_html__( 'Add New', 'wpo-aom' )
		);

		$table->display_tab_content();
	}

	/**
	 * Render the create screen for a new custom order status.
	 *
	 * @return void
	 */
	private function render_create_screen(): void {
		wc_back_header(
			__( 'Add Custom Order Status', 'wpo-aom' ),
			__( 'Custom Order Statuses', 'wpo-aom' ),
			admin_url( 'admin.php?page=wc-settings&tab=wpo_aom_custom_status_tab' )
		);

		woocommerce_admin_fields( $this->get_edit_settings_fields() );
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

		// Display back header and edit form.
		wc_back_header(
			__( 'Edit Custom Order Status', 'wpo-aom' ),
			__( 'Custom Order Statuses', 'wpo-aom' ),
			admin_url( 'admin.php?page=wc-settings&tab=wpo_aom_custom_status_tab' )
		);

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
				'type' => 'title',
				'id'   => $option_name,
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
					'pattern' => '^[a-z0-9_-]+$',
					'title'   => esc_html__( 'Only lowercase letters, numbers, hyphens, and underscores are allowed.', 'wpo-aom' ),
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
		$data = array(
			'status_key' => sanitize_text_field( $_POST['wpo_aom_custom_order_status_key'] ?? '' ),
			'label'      => sanitize_text_field( $_POST['wpo_aom_custom_order_status_label'] ?? '' ),
			'background' => sanitize_hex_color( $_POST['wpo_aom_custom_order_status_background'] ?? '' ),
		);

		switch ( $this->get_current_action() ) {
			case 'edit':
				$status_id = absint( $_GET['status_id'] ?? 0 );
				WPO_AOM()->custom_order_status->update( $status_id, $data );
				break;
			case 'create':
				WPO_AOM()->custom_order_status->create( $data );
				wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=wpo_aom_custom_status_tab' ) );
				break;
		}
	}

	/**
	 * Handle the AJAX request to delete a custom order status.
	 *
	 * @return void
	 */
	public function ajax_delete_custom_order_status(): void {
		if (
			! current_user_can( 'manage_woocommerce' ) ||
			! check_ajax_referer( 'wpo_aom_cos', '_wpnonce', false ) ||
			empty( $_POST['status_id'] ) ||
			! is_numeric( $_POST['status_id'] )
		) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid request.', 'wpo-aom' ) ) );
		}

		$status_id = absint( $_POST['status_id'] );
		$deleted   = WPO_AOM()->custom_order_status->delete( $status_id );

		if ( $deleted ) {
			wp_send_json_success( array( 'message' => esc_html__( 'Custom order status deleted successfully.', 'wpo-aom' ) ) );
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to delete custom order status.', 'wpo-aom' ) ) );
		}

		wp_die();
	}

	/**
	 * Get the current action from the request.
	 *
	 * @return string
	 */
	private function get_current_action(): string {
		$action = $_REQUEST['action'] ?? '';

		// Only allow specific actions.
		if ( ! in_array( $action, array( 'edit', 'create' ), true ) ) {
			$action = '';
		}

		return $action;
	}

}
