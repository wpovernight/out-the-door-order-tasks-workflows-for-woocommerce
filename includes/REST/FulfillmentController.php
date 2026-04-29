<?php

namespace WPO\AOM\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WPO\AOM\Enums\FulfillmentStatuses;
use WPO\AOM\Services\FulfillmentService;

defined( 'ABSPATH' ) || exit;

class FulfillmentController extends BaseRestController {
	protected string $resource_name = 'fulfillments';

	public function register_routes(): void {
		/**
		 * Endpoint to retrieve orders based on their fulfillment status.
		 *
		 * Example: GET /wc/v3/wpo/aom/fulfillments/orders?status=fulfilled
		 * Query Parameter:
		 * - status (string, optional): The fulfillment status to filter orders by.
		 *   Valid values are 'not-fulfilled', 'fulfilled', and 'partially-fulfilled'.
		 * Response:
		 * - 200 OK: Returns a list of orders matching the specified fulfillment status.
		 * - 400 Bad Request: If the 'status' parameter is missing or contains an invalid value.
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/orders',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_orders' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Handle GET requests to retrieve orders based on their fulfillment status.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_orders( WP_REST_Request $request ) {
		$status = $request->get_param( 'status' ) ?? '';

		if ( ! empty( $status ) && ! FulfillmentStatuses::is_valid( $status ) ) {
			return new WP_Error(
				'invalid_status',
				__( 'Invalid fulfillment status provided.', 'wpo-advanced-order-manager' ),
				array( 'status' => 400 )
			);
		}

		/** @var FulfillmentService $fulfillment_service */
		$fulfillment_service = WPO_AOM()->get_service( FulfillmentService::class );

		$orders = $fulfillment_service->get_orders_by_fulfillment_status( $status );
		$data   = array();

		foreach ( $orders as $order ) {
			$order_data = $this->format_order( $order, $fulfillment_service );

			if ( ! empty( $order_data['items'] ) ) {
				$data[] = $order_data;
			}
		}

		/**
		 * Filter the fulfillment orders response.
		 *
		 * @param array  $data    The formatted order data.
		 * @param string $status  The requested fulfillment status filter.
		 */
		$data = apply_filters( 'wpo_aom_rest_get_fulfillment_orders', $data, $status );

		return rest_ensure_response( $data );
	}

	/**
	 * Format a single order into a structured response.
	 *
	 * @param \WC_Abstract_Order $order
	 * @param FulfillmentService $fulfillment_service
	 *
	 * @return array
	 */
	private function format_order( \WC_Abstract_Order $order, FulfillmentService $fulfillment_service ): array {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$items[] = $this->format_order_item( $item, $fulfillment_service );
		}

		return array(
			'order_id'           => $order->get_id(),
			'order_url'          => $order->get_edit_order_url(),
			'fulfillment_status' => $order->get_meta( FulfillmentService::ORDER_FULFILLMENT_STATUS_META_KEY )
				?: FulfillmentStatuses::NOT_FULFILLED,
			'customer_name'      => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'customer_location'  => $this->format_location( $order ),
			'items'              => $items,
		);
	}

	/**
	 * Format a single order item with its fulfillment data.
	 *
	 * Returns null for fully-fulfilled items (they don't need attention).
	 *
	 * @param \WC_Order_Item_Product $item
	 * @param FulfillmentService $fulfillment_service
	 *
	 * @return array
	 */
	private function format_order_item( \WC_Order_Item_Product $item, FulfillmentService $fulfillment_service ): array {
		$quantity           = (int) $item->get_quantity();
		$fulfillment_data   = $fulfillment_service->get_order_item_fulfillment_data( $item );
		$fulfilled_quantity = 0;

		if ( ! empty( $fulfillment_data ) ) {
			foreach ( $fulfillment_data as $fulfillment ) {
				$fulfilled_quantity += (int) $fulfillment->quantity;
			}
		}

		$product    = $item->get_product();
		$image_url  = '';
		$attributes = array();

		if ( $product ) {
			$image_id  = $product->get_image_id();
			$image_url = $image_id
				? wp_get_attachment_image_url( $image_id, 'thumbnail' )
				: wc_placeholder_img_src( 'thumbnail' );

			if ( $product instanceof \WC_Product_Variation ) {
				$attributes = $this->format_variation_attributes( $product );
			}
		}

		if ( $fulfilled_quantity >= $quantity ) {
			$item_status = FulfillmentStatuses::FULFILLED;
		} elseif ( $fulfilled_quantity > 0 ) {
			$item_status = FulfillmentStatuses::PARTIALLY_FULFILLED;
		} else {
			$item_status = FulfillmentStatuses::NOT_FULFILLED;
		}

		return array(
			'item_id'            => $item->get_id(),
			'name'               => $item->get_name(),
			'image_url'          => $image_url ?: '',
			'attributes'         => $attributes,
			'quantity'           => $quantity,
			'fulfilled_quantity' => $fulfilled_quantity,
			'fulfillment_status' => $item_status,
		);
	}

	/**
	 * Format variation attributes as a readable string.
	 *
	 * @param \WC_Product_Variation $variation
	 *
	 * @return array
	 */
	private function format_variation_attributes( \WC_Product_Variation $variation ): array {
		$attributes = $variation->get_attributes();
		$parts      = array();

		foreach ( $attributes as $taxonomy => $value ) {
			$label = wc_attribute_label( $taxonomy, $variation );

			// Resolve term slug to term name for global attributes.
			if ( taxonomy_exists( $taxonomy ) && ! empty( $value ) ) {
				$term = get_term_by( 'slug', $value, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$value = $term->name;
				}
			}

			if ( ! empty( $value ) ) {
				$parts[] = array(
					'label' => $label,
					'value' => $value,
				);
			}
		}

		return $parts;
	}

	/**
	 * Format the customer's billing address as a location string.
	 *
	 * @param \WC_Abstract_Order $order
	 *
	 * @return array
	 */
	private function format_location( \WC_Abstract_Order $order ): array {
		$city         = $order->get_shipping_city() ?: $order->get_billing_city();
		$state_code   = $order->get_shipping_state() ?: $order->get_billing_state();
		$country_code = $order->get_shipping_country() ?: $order->get_billing_country();

		$country = $country_code;
		$state   = $state_code;

		if ( isset( \WC()->countries ) ) {
			$countries = \WC()->countries->get_countries();
			$country   = $countries[ $country_code ] ?? $country_code;

			if ( $country_code && $state_code ) {
				$states = \WC()->countries->get_states( $country_code );
				$state  = $states[ $state_code ] ?? $state_code;
			}
		}

		return array(
			'city'    => $city,
			'state'   => $state,
			'country' => $country,
		);
	}
}
