<?php

namespace WPO\AOM\Admin\OrderEdit;

defined( 'ABSPATH' ) || exit;

use WPO\AOM\Enums\FulfillmentStatuses;
use WPO\AOM\Models\Fulfillment;
use WPO\AOM\Services\FulfillmentService;

final class Screen {
	private FulfillmentService $fulfillment_service;

	/**
	 * Constructor
	 *
	 * @param FulfillmentService $fulfillment_service
	 */
	public function __construct( FulfillmentService $fulfillment_service ) {
		$this->fulfillment_service = $fulfillment_service;
	}

	/**
	 * Register meta box.
	 *
	 * @return void
	 */
	public function register(): void {
		// Add meta box to order edit screen.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );

		// Add fulfillment column header in order items table.
		add_action( 'woocommerce_admin_order_item_headers', array( $this, 'order_items_headers' ) );

		// Add fulfillment column values in order items table.
		add_action( 'woocommerce_admin_order_item_values', array( $this, 'order_items_values' ), 10, 3 );

		// Save fulfillment quantity changes.
		add_action( 'woocommerce_before_save_order_items', array( $this, 'on_save_order_items' ), 10, 2 );

		// Cleanup fulfillment data on order item deletion.
		add_action( 'woocommerce_before_delete_order_item', array( $this, 'on_delete_order_item' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX handler for saving fulfillment.
		add_action( 'wp_ajax_wpo_aom_save_fulfillment', array( $this, 'ajax_save_fulfillment' ) );
	}

	/**
	 * Add meta box to the order edit screen.
	 *
	 * @param string $post_type
	 * @param \WP_Post|\Automattic\WooCommerce\Admin\Overrides\Order $post
	 *
	 * @return void
	 */
	public function add_meta_box( string $post_type, object $post ): void {
		$screen_id = is_callable( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

		if ( $screen_id !== $post_type ) {
			return;
		}

		add_meta_box(
			'wpo-aom-order-meta-box',
			esc_html__( 'Advanced Order Management', 'wpo-advanced-order-manager' ),
			array( $this, 'render_meta_box' ),
			$screen_id,
			'side',
			'default'
		);
	}

	/**
	 * Enqueue scripts for the order edit metabox.
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		$screen          = get_current_screen();
		$valid_screen_id = is_callable( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

		if ( ! $screen || $valid_screen_id !== $screen->id ) {
			return;
		}

		// The screen ID is shared between the orders list and the edit/new screens (especially on HPOS).
		// Restrict to the edit/new context only.
		$action         = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$is_edit_screen = in_array( $action, array( 'edit', 'new' ), true ) || 'add' === $screen->action;

		if ( ! $is_edit_screen ) {
			return;
		}

		$order_id = 0;

		// Preferred: WooCommerce sets $GLOBALS['theorder'] on the order edit screen
		// for both HPOS and legacy CPT storage.
		if ( isset( $GLOBALS['theorder'] ) && $GLOBALS['theorder'] instanceof \WC_Order ) {
			$order_id = absint( $GLOBALS['theorder']->get_id() );
		}

		// Fallback: resolve from query args (HPOS uses `id`, legacy uses `post`).
		if ( 0 === $order_id ) {
			if ( isset( $_GET['id'] ) ) {
				$order_id = absint( wp_unslash( $_GET['id'] ) );
			} elseif ( isset( $_GET['post'] ) ) {
				$order_id = absint( wp_unslash( $_GET['post'] ) );
			}
		}

		// Fallback: global $post (legacy auto-draft).
		if ( 0 === $order_id ) {
			global $post;
			if ( $post instanceof \WP_Post && $post->ID > 0 ) {
				$order_id = absint( $post->ID );
			}
		}

		// Verify it's a valid order.
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				$order_id = 0;
			}
		}

		// Do not need the suffix, since it's a React app and we are using webpack to handle the minification.
		wp_enqueue_script(
			'wpo-aom-order-edit-metabox',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/js/order-edit-metabox.js',
			array( 'wp-element', 'wp-components', 'wp-i18n' ),
			WPO_AOM_VERSION,
			true
		);

		wp_set_script_translations( 'wpo-aom-order-edit-metabox', 'wpo-advanced-order-manager', WPO_AOM()->plugin_path() . '/languages' );

		wp_localize_script(
			'wpo-aom-order-edit-metabox',
			'WPO_AOM_OrderEdit_MetaBox',
			array(
				'orderId'                  => absint( $order_id ),
				'apiRoot'                  => esc_url_raw( rest_url( '/wc/v3' ) ),
				'apiNamespace'             => 'wpo/aom',
				'nonce'                    => wp_create_nonce( 'wp_rest' ),
				'isWooFulfillmentsEnabled' => wc_string_to_bool( get_option( 'woocommerce_feature_fulfillments_enabled', 'no' ) ),
				'archivePageUrl'           => esc_url( admin_url( 'admin.php?page=wpo_aom_order_manager#/task-manager/archive' ) ),
			)
		);

		wp_enqueue_script(
			'wpo-aom-order-edit',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/js/order-edit.js',
			array(),
			WPO_AOM_VERSION,
			true
		);

		wp_localize_script(
			'wpo-aom-order-edit',
			'WPO_AOM_OrderEdit',
			array(
				'nonce' => wp_create_nonce( 'wpo_aom_order_edit' ),
			)
		);

		wp_enqueue_style(
			'wpo-aom-admin-common',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/common.css',
			array(),
			WPO_AOM_VERSION
		);

		wp_enqueue_style(
			'wpo-aom-admin-skeleton',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/skeleton.css',
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
			'wpo-aom-admin-order-edit',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/css/order-edit.css',
			array(),
			WPO_AOM_VERSION
		);
	}

	/**
	 * Render the meta box content.
	 *
	 * @return void
	 */
	public function render_meta_box(): void {
		echo '<div id="wpo-aom-order-meta-box-content"></div>';
	}

	/**************************
	 * Fulfillment
	 **************************/

	/**
	 * Add fulfillment column header in order items table.
	 *
	 * @return void
	 */
	public function order_items_headers(): void {
		echo '<th>' . esc_html__( 'Fulfillments', 'wpo-advanced-order-manager' ) . '</th>';
	}

	/**
	 * Add fulfillment column values in order items table.
	 *
	 * @param \WC_Product|bool $product
	 * @param \WC_Order_Item|object $item
	 * @param int $item_id
	 *
	 * @return void
	 */
	public function order_items_values( $product, $item, int $item_id ): void {
		// Display only for product line items.
		if ( ! $product instanceof \WC_Product || ! $item instanceof \WC_Order_Item ) {
			return;
		}

		$fulfillment_data = $this->fulfillment_service->get_order_item_fulfillment_data( $item );

		// For MVP, we only store one fulfillment entry per item.
		$fulfillment = $fulfillment_data[0] ?? null;

		$fulfillment_status = $fulfillment ?
			$this->fulfillment_service->get_order_item_fulfillment_status( $item, $fulfillment )
			: FulfillmentStatuses::NOT_FULFILLED;

		$fulfillment_quantity    = $fulfillment ? (int) $fulfillment->quantity : 0;
		$total_quantity          = (int) $item->get_quantity();
		// Remove partial refunded items from total quantity.
		$order = $item->get_order();
		if ( $order instanceof \WC_Abstract_Order ) {
			$total_quantity += (int) $order->get_qty_refunded_for_item( $item_id );
		}

		// View mode
		$fulfillment_status_html = $this->get_fulfillment_status_html( $fulfillment_status, $fulfillment_quantity, $total_quantity );
		$edit_button_html        = sprintf(
			'<button type="button" class="wpo-button wpo-button-icon wpo-aom-edit-fulfillment" data-item-id="%1$d" title="%2$s">
				<span class="screenReader">%2$s</span>
			</button>',
			esc_attr( $item_id ),
			esc_html__( 'Edit fulfillment quantity', 'wpo-advanced-order-manager' )
		);
		$view_html               = sprintf( '<div class="view">%s%s</div>', $fulfillment_status_html, $edit_button_html );

		// Edit mode
		$edit_input_html  = sprintf(
			'<label>
				<input
					type="number"
					name="wpo-aom-fulfillment-quantity[%1$d][%4$s]"
					min="0"
					max="%2$d"
					class="wpo-aom-fulfillment-quantity"
					value="%3$d"
					data-fulfillment-id="%4$s"
				/>
				<span class="screenReader">%5$s</span>
			</label>',
			esc_attr( $item_id ),
			esc_attr( $total_quantity ),
			esc_attr( $fulfillment_quantity ),
			esc_attr( $fulfillment ? $fulfillment->id : '' ),
			esc_html__( 'Fulfillment Quantity', 'wpo-advanced-order-manager' )
		);
		$edit_button_html = sprintf(
			'<ul class="wpo-aom-fulfillment-actions" style="display: none;">
					<li>
						<button
							type="button"
							class="wpo-button wpo-button-icon wpo-aom-save-fulfillment"
							data-item-id="%1$d"
							data-fulfillment-id="%2$s"
							title="%3$s"
						>
							<span class="screenReader">%3$s</span>
						</button>
					</li>
					<li>
						<button
							type="button"
							class="wpo-button wpo-button-icon wpo-aom-cancel-fulfillment"
							data-item-id="%1$d"
							data-fulfillment-id="%2$s"
							title="%4$s"
						>
							<span class="screenReader">%4$s</span>
						</button>
					</li>
				</ul>',
			esc_attr( $item_id ),
			esc_attr( $fulfillment ? $fulfillment->id : '' ),
			esc_html__( 'Save fulfillment quantity', 'wpo-advanced-order-manager' ),
			esc_html__( 'Cancel fulfillment edit', 'wpo-advanced-order-manager' )
		);
		$edit_html        = sprintf(
			'<div class="edit" style="display:none;">%s%s</div>',
			$edit_input_html,
			$edit_button_html
		);


		// Output the fulfillment cell.
		printf(
			'<td class="wpo-aom-fulfillment">%s%s</td>',
			$view_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$edit_html  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Get fulfillment status HTML.
	 *
	 * @param string $fulfillment_status
	 * @param int|null $shipped_quantity
	 * @param int|null $total_quantity
	 *
	 * @return string
	 */
	public function get_fulfillment_status_html( string $fulfillment_status, int $shipped_quantity, int $total_quantity ): string {
		switch ( $fulfillment_status ) {
			case FulfillmentStatuses::FULFILLED:
				$class = 'fully-fulfilled';
				break;
			case FulfillmentStatuses::PARTIALLY_FULFILLED:
				$class = 'partially-fulfilled';
				break;
			default:
			case FulfillmentStatuses::NOT_FULFILLED:
				$class = 'not-fulfilled';
				break;
		}

		$label = sprintf(
			'%1$d / %2$d %3$s',
			$shipped_quantity,
			$total_quantity,
			esc_html__( 'fulfilled', 'wpo-advanced-order-manager' )
		);


		return sprintf(
			'<span class="wpo-aom-tag %1$s">%2$s</span>',
			esc_attr( $class ),
			$label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Handle saving of fulfillment quantities for order items.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $items Order items to save.
	 *
	 * @return void
	 */
	public function on_save_order_items( int $order_id, array $items ): void {
		if (
			! isset( $items['wpo-aom-fulfillment-quantity'] ) ||
			! is_array( $items['wpo-aom-fulfillment-quantity'] ) ) {
			return;
		}

		foreach ( $items['wpo-aom-fulfillment-quantity'] as $item_id => $fulfillment_data ) {
			$item_id = absint( $item_id );
			if ( ! $item_id || ! is_array( $fulfillment_data ) || empty( $fulfillment_data ) ) {
				continue;
			}

			$fulfillment_id       = absint( array_key_first( $fulfillment_data ) );
			$fulfillment_quantity = isset( $fulfillment_data[ $fulfillment_id ] )
				? absint( $fulfillment_data[ $fulfillment_id ] )
				: 0;

			$this->fulfillment_service->save_order_item_fulfillment_quantity(
				$item_id,
				$fulfillment_quantity,
				$fulfillment_id > 0 ? $fulfillment_id : null
			);
		}

		// Update the order-level fulfillment status cache.
		$this->fulfillment_service->update_order_fulfillment_status_meta( $order_id );
	}

	/**
	 * Handle cleanup of fulfillment data when an order item is deleted.
	 *
	 * @param int $item_id Order item ID.
	 *
	 * @return void
	 */
	public function on_delete_order_item( int $item_id ): void {
		// Resolve the order before deleting fulfillment data.
		$order_item = \WC_Order_Factory::get_order_item( $item_id );
		$order_id   = $order_item ? $order_item->get_order_id() : 0;

		if ( $order_id > 0 ) {
			$this->fulfillment_service->delete_order_item_fulfillment_data( $item_id );
			$this->fulfillment_service->update_order_fulfillment_status_meta( $order_id );
		}
	}

	/**
	 * AJAX handler for saving fulfillment quantity.
	 *
	 * @return void
	 */
	public function ajax_save_fulfillment(): void {
		// Check nonce for security.
		if (
			! isset( $_POST['nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpo_aom_order_edit' )
		) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security verification failed.', 'wpo-advanced-order-manager' ),
				)
			);
		}

		// Check user capabilities.
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'You do not have permission to perform this action.', 'wpo-advanced-order-manager' ),
				)
			);
		}

		// Get and validate parameters.
		$item_id        = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$fulfillment_id = isset( $_POST['fulfillment_id'] ) ? absint( $_POST['fulfillment_id'] ) : null;
		$quantity       = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;

		if ( $item_id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Invalid item ID.', 'wpo-advanced-order-manager' ),
				)
			);
		}

		// Get the order item to validate and get total quantity.
		$order_item = \WC_Order_Factory::get_order_item( $item_id );
		if ( ! $order_item ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Order item not found.', 'wpo-advanced-order-manager' ),
				)
			);
		}

		$total_quantity = (int) $order_item->get_quantity();
		if ( $quantity > $total_quantity ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: total quantity */
						esc_html__( 'Fulfillment quantity cannot exceed %d.', 'wpo-advanced-order-manager' ),
						$total_quantity
					),
				)
			);
		}

		// Save the fulfillment quantity.
		$saved_fulfillment_id = $this->fulfillment_service->save_order_item_fulfillment_quantity(
			$item_id,
			$quantity,
			$fulfillment_id
		);

		if ( null === $saved_fulfillment_id ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Failed to save fulfillment quantity.', 'wpo-advanced-order-manager' ),
				)
			);
		}

		// Update the order-level fulfillment status cache.
		$this->fulfillment_service->update_order_fulfillment_status_meta( $order_item->get_order_id() );

		// Temporarily create a fulfillment object to be able to call `get_order_item_fulfillment_status()`,
		// and get the updated status for the response.
		$fulfillment = new Fulfillment(
			array(
				'id'       => $saved_fulfillment_id,
				'quantity' => $quantity,
			)
		);

		$fulfillment_status = $this->fulfillment_service->get_order_item_fulfillment_status( $order_item, $fulfillment );


		// Register an order note to record the fulfillment quantity change.
		$order = $order_item->get_order();
		if ( $order ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: product name, 2: fulfilled quantity, 3: total quantity */
					esc_html__( 'Fulfillment updated for "%1$s": %2$d of %3$d fulfilled.', 'wpo-advanced-order-manager' ),
					$order_item->get_name(),
					$quantity,
					$total_quantity
				)
			);
		}

		// Return the updated HTML.
		wp_send_json_success(
			array(
				'message'        => esc_html__( 'Fulfillment quantity saved successfully.', 'wpo-advanced-order-manager' ),
				'html'           => $this->get_fulfillment_status_html( $fulfillment_status, $quantity, $total_quantity ),
				'fulfillment_id' => $saved_fulfillment_id,
			)
		);
	}
}
