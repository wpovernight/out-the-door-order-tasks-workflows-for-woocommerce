<?php

namespace WPO\AOM\Services;

use WPO\AOM\Enums\FulfillmentStatuses;
use WPO\AOM\Models\Fulfillment;

defined( 'ABSPATH' ) || exit;

final class FulfillmentService {
	public const FULFILLMENT_DATA_META_KEY            = '_wpo_aom_fulfillment_data';
	public const ORDER_FULFILLMENT_STATUS_META_KEY   = '_wpo_aom_fulfillment_status';

	/**
	 * Get fulfillment data for an order item.
	 *
	 * @param int|\WC_Order_Item|\WC_Order_Item_Product $item
	 *
	 * @return Fulfillment[]|null
	 */
	public function get_order_item_fulfillment_data( $item ): ?array {
		if ( is_numeric( $item ) ) {
			if ( $item <= 0 ) {
				return null;
			}

			$fulfillment_meta = wc_get_order_item_meta( intval( $item ), self::FULFILLMENT_DATA_META_KEY, true );
		} elseif ( $item instanceof \WC_Order_Item || $item instanceof \WC_Order_Item_Product ) {
			$fulfillment_meta = $item->get_meta( self::FULFILLMENT_DATA_META_KEY, true );
		} else {
			return null;
		}

		if ( empty( $fulfillment_meta ) || ! is_array( $fulfillment_meta ) ) {
			return null;
		}

		return array_map( fn( $data ) => new Fulfillment( $data ), $fulfillment_meta );
	}

	/**
	 * Get fulfillment status for an order.
	 *
	 * @param \WC_Abstract_Order $order
	 *
	 * @return string
	 */
	public function get_order_fulfillment_status( \WC_Abstract_Order $order ): string {
		$shipped_quantity = 0;
		$total_quantity   = 0;

		foreach ( $order->get_items() as $item ) {
			$item_quantity  = (int) $item->get_quantity();
			$total_quantity += $item_quantity;

			$item_fulfillment_data = $this->get_order_item_fulfillment_data( $item );

			if ( empty( $item_fulfillment_data ) ) {
				continue;
			}

			foreach ( $item_fulfillment_data as $fulfillment ) {
				$shipped_quantity += (int) $fulfillment->quantity;
			}
		}

		if ( 0 === $shipped_quantity ) {
			return FulfillmentStatuses::NOT_FULFILLED;
		}

		if ( $shipped_quantity >= $total_quantity ) {
			return FulfillmentStatuses::FULFILLED;
		}

		return FulfillmentStatuses::PARTIALLY_FULFILLED;
	}

	/**
	 * Get fulfillment status for an order item based on a given fulfillment.
	 *
	 * For now, each item displays one fulfillment entry at a time, so we don't need to sum across multiple fulfillments.
	 * In the future, if we support multiple simultaneous fulfillments per item, this logic may need to be updated.
	 *
	 * @param \WC_Order_Item $item
	 * @param Fulfillment $fulfillment
	 *
	 * @return string
	 */
	public function get_order_item_fulfillment_status( \WC_Order_Item $item, Fulfillment $fulfillment ): string {
		$shipped_quantity = (int) $fulfillment->quantity;
		if ( 0 === $shipped_quantity ) {
			return FulfillmentStatuses::NOT_FULFILLED;
		}

		$item_quantity = (int) $item->get_quantity();
		if ( $shipped_quantity === $item_quantity ) {
			return FulfillmentStatuses::FULFILLED;
		} else {
			return FulfillmentStatuses::PARTIALLY_FULFILLED;
		}
	}

	/**
	 * Save fulfillment quantity for an order item.
	 *
	 * @param int $item_id
	 * @param int $quantity
	 * @param int|null $fulfillment_id
	 *
	 * @return bool
	 */
	public function save_order_item_fulfillment_quantity( int $item_id, int $quantity, ?int $fulfillment_id ): bool {
		$fulfillment_data = $this->get_order_item_fulfillment_data( $item_id ) ?? [];

		$is_updated = false;
		foreach ( $fulfillment_data as $index => $fulfillment ) {
			if ( $fulfillment->id === $fulfillment_id ) {
				$fulfillment->quantity      = $quantity;
				$is_updated                 = true;
				$fulfillment_data[ $index ] = $fulfillment;
				break;
			}
		}

		// If not updated, create a new fulfillment entry.
		if ( ! $is_updated ) {
			// Find the next available ID.
			$new_id = empty( $fulfillment_data )
				? 1
				: max( array_map( fn( $f ) => $f->id, $fulfillment_data ) ) + 1;

			$new_fulfillment    = new Fulfillment(
				array(
					'id'       => $new_id,
					'quantity' => $quantity,
				)
			);
			$fulfillment_data[] = $new_fulfillment;
		}

		$fulfillment_data_array = array_map( fn( $f ) => $f->to_array(), $fulfillment_data );

		$existing = wc_get_order_item_meta( $item_id, self::FULFILLMENT_DATA_META_KEY, true );
		if ( $existing === $fulfillment_data_array ) {
			return true;
		}

		return (bool) wc_update_order_item_meta( $item_id, self::FULFILLMENT_DATA_META_KEY, $fulfillment_data_array );
	}

	/**
	 * Delete fulfillment data for an order item.
	 *
	 * @param int $item_id
	 *
	 * @return bool
	 */
	public function delete_order_item_fulfillment_data( int $item_id ): bool {
		return wc_delete_order_item_meta( $item_id, self::FULFILLMENT_DATA_META_KEY );
	}

	/**
	 * Recalculate and persist the order-level fulfillment status as order meta.
	 *
	 * This is a denormalized cache that enables efficient queries.
	 *
	 * @param \WC_Abstract_Order|int $order_or_id Order object or order ID.
	 *
	 * @return string The computed fulfillment status, or empty string on failure.
	 */
	public function update_order_fulfillment_status_meta( $order ): string {
		$order = $order instanceof \WC_Abstract_Order
			? $order
			: wc_get_order( $order );

		if ( ! $order ) {
			return '';
		}

		$status = $this->get_order_fulfillment_status( $order );

		$order->update_meta_data( self::ORDER_FULFILLMENT_STATUS_META_KEY, $status );
		$order->save_meta_data();

		return $status;
	}

	/**
	 * Query orders by their cached fulfillment status.
	 *
	 * Pass an empty string to retrieve all orders that have any fulfillment data.
	 *
	 * @param string $status One of FulfillmentStatuses constants, or empty for all.
	 * @param array  $args   Additional wc_get_orders() arguments.
	 *
	 * @return \WC_Abstract_Order[]
	 */
	public function get_orders_by_fulfillment_status( string $status = '', array $args = array() ): array {
		if ( ! empty( $status ) && ! FulfillmentStatuses::is_valid( $status ) ) {
			return array();
		}

		/**
		 * Filter the number of days to look back when querying orders by fulfillment status.
		 *
		 * @param int $days Number of days. Default 60. Set to 0 for no date limit.
		 */
		$days = (int) apply_filters( 'wpo_aom_fulfillment_status_query_days', 60 );

		$defaults = array(
			'meta_key' => self::ORDER_FULFILLMENT_STATUS_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'limit'    => -1,
			'status'   => 'any',
		);

		if ( ! empty( $status ) ) {
			$defaults['meta_value'] = $status; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		} else {
			$defaults['meta_compare'] = 'EXISTS'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		if ( $days > 0 ) {
			$defaults['date_created'] = '>' . gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		}

		/**
		 * Filter the wc_get_orders() arguments for fulfillment status queries.
		 *
		 * @param array  $query_args The merged query arguments.
		 * @param string $status     The fulfillment status being queried (empty = all).
		 */
		$query_args = apply_filters( 'wpo_aom_fulfillment_status_query_args', wp_parse_args( $args, $defaults ), $status );

		return wc_get_orders( $query_args );
	}
}
