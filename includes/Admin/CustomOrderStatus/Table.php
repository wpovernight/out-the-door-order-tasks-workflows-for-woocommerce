<?php

namespace WPO\AOM\Admin\CustomOrderStatus;

use WP_List_Table;
use WPO\AOM\Models\CustomOrderStatus;
use WPO\AOM\Services\CustomOrderStatusService;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'custom_order_status',
			'plural'   => 'custom_order_statuses',
			'ajax'     => false
		) );
	}

	/**
	 * Render the table UI.
	 *
	 * @return void
	 */
	public function display_tab_content(): void {
		echo '<div class="wpo_aom_settings_tab">';

		do_action( 'wpo_aom_before_custom_order_status_settings_tab' );

		$this->prepare_items();
		$this->views();
		$this->display();

		do_action( 'wpo_aom_after_custom_order_status_settings_tab' );

		echo '</div>';
	}

	/**
	 * Define the columns of the table.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'label'          => esc_html__( 'Label', 'wpo-aom' ),
			'status_preview' => esc_html__( 'Preview', 'wpo-aom' ),
			'actions'        => esc_html__( 'Actions', 'wpo-aom' ),
		);
	}

	/**
	 * Prepare the items for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$columns             = $this->get_columns();
		$per_page            = $this->get_items_per_page( 'report_items_per_page', 20 );
		$current_page_number = $this->get_pagenum();

		/** @var CustomOrderStatusService $custom_statuses_service */
		$custom_statuses_service = WPO_AOM()->get_service( CustomOrderStatusService::class );
		$all_custom_statuses     = $custom_statuses_service->all();
		$total_items             = count( $all_custom_statuses );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
		) );

		$this->_column_headers = array( $columns, array(), array() );
		$this->items           = apply_filters(
			'wpo_aom_custom_order_statuses_table_items',
			array_slice( $all_custom_statuses, ( ( $current_page_number - 1 ) * $per_page ), $per_page )
		);
	}

	/**
	 * No items found message.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No custom order statuses found.', 'wpo-aom' );
	}

	/** ================================
	 *   Render columns
	 *  ================================ */

	/**
	 * Render the Label column.
	 *
	 * @param CustomOrderStatus $item
	 *
	 * @return string
	 */
	protected function column_label( CustomOrderStatus $item ): string {
		return esc_html( $item->label );
	}

	/**
	 * Render the Preview column.
	 *
	 * @param CustomOrderStatus $item
	 *
	 * @return string
	 */
	protected function column_status_preview( CustomOrderStatus $item ): string {
		return sprintf(
			'<span class="wpo-aom-custom-order-status-preview order-status" style="background-color:%s; color: %s">%s</span>',
			esc_attr( $item->background ),
			esc_attr( $item->foreground ),
			esc_html( $item->label )
		);
	}

	/**
	 * Render the Actions column.
	 *
	 * @param CustomOrderStatus $item
	 *
	 * @return string
	 */
	protected function column_actions( CustomOrderStatus $item ): string {
		$edit_url = wp_nonce_url(
			add_query_arg(
				array( 'action' => 'edit', 'status_id' => $item->id ),
				admin_url( 'admin.php?page=wc-settings&tab=wpo_aom_custom_status_tab' )
			),
			'wpo_aom_edit_custom_order_status'
		);

		$edit_button   = sprintf(
			'<a href="%s" class="wpo-aom-custom-order-status-edit" data-status-id="%d"><span class="dashicons dashicons-edit"></span>%s</a>',
			$edit_url,
			$item->id,
			'<span class="screen-reader-text">' . esc_html__( 'Edit', 'wpo-aom' ) . '</span>'
		);
		$delete_button = sprintf(
			'<button class="wpo-aom-custom-order-status-delete" data-status-id="%d"><span class="dashicons dashicons-trash"></span>%s</button>',
			$item->id,
			'<span class="screen-reader-text">' . esc_html__( 'Delete', 'wpo-aom' ) . '</span>'
		);

		return '<ul class="wpo-aom-custom-order-status-actions"><li>' . $edit_button . '</li><li>' . $delete_button . '</li></ul>';
	}

	/**
	 * Render default column.
	 *
	 * @param $item
	 * @param $column_name
	 *
	 * @return void
	 */
	protected function column_default( $item, $column_name ): void {
		do_action( 'wpo_aom_custom_order_status_default_column', $item, $column_name );
	}

}
