<?php

namespace WPO\AOM\Admin\OrderEdit;

defined( 'ABSPATH' ) || exit;

use WPO\AOM\Enums\FulfillmentStatuses;
use WPO\AOM\Services\FulfillmentService;

final class MetaBox {
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

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
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
			esc_html__( 'Advanced Order Management', 'wpo-aom' ),
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

		$order_id = 0;

		// Get order ID based on storage type
		if ( isset( $_GET['post'] ) ) {
			$order_id = absint( $_GET['post'] );
		} elseif ( isset( $_GET['id'] ) ) {
			$order_id = absint( $_GET['id'] );
		}

		// Verify it's a valid order
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				$order_id = 0;
			}
		}

		wp_enqueue_script(
			'wpo-aom-order-edit',
			WPO_AOM()->plugin_url() . '/includes/Admin/assets/js/order-edit.js',
			array( 'wp-element', 'wp-components' ),
			WPO_AOM_VERSION,
			true
		);

		wp_localize_script(
			'wpo-aom-order-edit',
			'WPO_AOM_OrderEdit',
			array(
				'orderId'               => absint( $order_id ),
				'apiRoot'               => esc_url_raw( rest_url( '/wc/v3' ) ),
				'apiNamespace'          => 'wpo/aom',
				'nonce'                 => wp_create_nonce( 'wp_rest' ),
				'isFulfillmentsEnabled' => wc_string_to_bool( get_option( 'woocommerce_feature_fulfillments_enabled', 'no' ) ),
				'i18n'                  => array(
					'loading'          => esc_html__( 'Loading...', 'wpo-aom' ),
					'errorLoading'     => esc_html__( 'Error loading tasks. Please try again.', 'wpo-aom' ),
					'confirmationText' => esc_html__( 'Are you sure?' ),
					'tasks'            => array(
						'sectionTitle'         => esc_html__( 'Tasks', 'wpo-aom' ),
						'addTask'              => esc_html__( 'Add Task', 'wpo-aom' ),
						'editTask'             => esc_html__( 'Edit Task', 'wpo-aom' ),
						'deleteTask'           => esc_html__( 'Delete Task', 'wpo-aom' ),
						'noTasks'              => esc_html__( 'No tasks found.', 'wpo-aom' ),
						'active'               => esc_html__( 'Active Tasks', 'wpo-aom' ),
						// ToDo: Finished status should be dynamic.
						'viewFinished'         => esc_html__( 'View Completed Tasks', 'wpo-aom' ),
						'hideFinished'         => esc_html__( 'Hide Completed Tasks', 'wpo-aom' ),
						'activeTasksHeading'   => esc_html__( 'Active Tasks', 'wpo-aom' ),
						'finishedTasksHeading' => esc_html__( 'Completed Tasks', 'wpo-aom' ),
					),
					'fulfillments'     => array(
						'addFulfillment' => esc_html__( 'Add Fulfillment', 'wpo-aom' ),
					),
					'form'             => array(
						'labels'       => array(
							'status'           => esc_html__( 'Status', 'wpo-aom' ),
							'priority'         => esc_html__( 'Priority', 'wpo-aom' ),
							'dueDate'          => esc_html__( 'Due Date', 'wpo-aom' ),
							'title'            => esc_html__( 'Title', 'wpo-aom' ),
							'description'      => esc_html__( 'Description', 'wpo-aom' ),
							'associatedOrders' => esc_html__( 'Associated Orders', 'wpo-aom' ),
						),
						'placeholders' => array(
							'select'          => esc_html__( 'Select', 'wpo-aom' ),
							'taskName'        => esc_html__( 'Write a name for your task.', 'wpo-aom' ),
							'taskDescription' => esc_html__( 'Describe the task.', 'wpo-aom' ),
							'searchOrders'    => esc_html__( 'Search orders by number, customer, address...', 'wpo-aom' ),
						),
					),
					'actions'          => array(
						'edit'           => esc_html__( 'Edit', 'wpo-aom' ),
						'editTask'       => esc_html__( 'Edit task', 'wpo-aom' ),
						'delete'         => esc_html__( 'Delete', 'wpo-aom' ),
						'deleteTask'     => esc_html__( 'Delete task', 'wpo-aom' ),
						'cancel'         => esc_html__( 'Cancel', 'wpo-aom' ),
						'clear'          => esc_html__( 'Clear', 'wpo-aom' ),
						'apply'          => esc_html__( 'Apply', 'wpo-aom' ),
						'actions'        => esc_html__( 'Actions', 'wpo-aom' ),
						'createTask'     => esc_html__( 'Create Task', 'wpo-aom' ),
						'updateTask'     => esc_html__( 'Update Task', 'wpo-aom' ),
						'markFinished'   => esc_html__( 'Mark as Completed', 'wpo-aom' ), // ToDo: Finished status should be dynamic.
						'markUnfinished' => esc_html__( 'Mark as In Progress', 'wpo-aom' ), // ToDo: Finished status should be dynamic.
					),
				),
			),
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
	 * Add fulfillment status column header in order items table.
	 *
	 * @return void
	 */
	public function order_items_headers(): void {
		echo '<th>' . __( 'Fulfillment Status', 'woocommerce-product-batch-numbers' ) . '</th>';
	}

	/**
	 * Add fulfillment status column values in order items table.
	 *
	 * @param \WC_Product|bool $product
	 * @param \WC_Order_Item $item
	 * @param int $item_id
	 *
	 * @return void
	 */
	public function order_items_values( $product, \WC_Order_Item $item, int $item_id ) {
		$fulfillment_data = $this->fulfillment_service->get_fulfillment_data( $item );

		// For MVP, we only store one fulfillment entry per item.
		$fulfillment = $fulfillment_data[0] ?? null;

		$fulfillment_status = $fulfillment ?
			$this->fulfillment_service->get_order_item_fulfillment_status( $item, $fulfillment_data[0] )
			: FulfillmentStatuses::NOT_FULFILLED;

		$fulfillment_quantity    = $fulfillment ? (int) $fulfillment->quantity : 0;
		$total_quantity          = (int) $item->get_quantity();
		$fulfillment_status_html = $this->get_fulfillment_status_html( $fulfillment_status, $fulfillment_quantity, $total_quantity );

		$edit_button = sprintf(
			'<button type="button" class="wpo-button wpo-button-icon wpo-aom-edit-fulfillment" data-item-id="%1$d">
				<span class="screenReader">%2$s</span>
			</button>',
			esc_attr( $item_id ),
			esc_html__( 'Edit', 'wpo-aom' )
		);

		printf( '<td class="wpo-aom-fulfillment-status"><div>%s%s</div></td>', $fulfillment_status_html, $edit_button );
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
	public function get_fulfillment_status_html( string $fulfillment_status, ?int $shipped_quantity, ?int $total_quantity ): string {
		switch ( $fulfillment_status ) {
			case FulfillmentStatuses::FULFILLED:
				$class = 'fully-shipped';
				$label = esc_html__( 'Fully Shipped', 'wpo-aom' );
				break;
			case FulfillmentStatuses::PARTIALLY_FULFILLED:
				$class = 'partially-shipped';
				$label = esc_html__( 'Partially Shipped', 'wpo-aom' );

				if ( ! is_null( $total_quantity ) && ! is_null( $shipped_quantity ) ) {
					$label .= sprintf( ' (%1$d/%2$d)', esc_html( $shipped_quantity ), esc_html( $total_quantity ) );
				}
				break;
			default:
			case FulfillmentStatuses::NOT_FULFILLED:
				$class = 'not-shipped';
				$label = esc_html__( 'Not Shipped', 'wpo-aom' );
				break;
		}

		return sprintf(
			'<span class="wpo-aom-tag %1$s">%2$s</span>',
			esc_attr( $class ),
			$label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
}
