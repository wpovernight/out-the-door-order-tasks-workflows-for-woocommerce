<?php

namespace WPO\AOM\Services;

use WPO\AOM\Enums\FulfillmentStatuses;
use WPO\AOM\Models\Fulfillment;

defined( 'ABSPATH' ) || exit;

final class FulfillmentService {
	public const FULFILLMENT_DATA_META_KEY = '_wpo_aom_fulfillment_data';

	/**
	 * Get fulfillment data for an order item.
	 *
	 * @param \WC_Order_Item $item
	 *
	 * @return Fulfillment[]|null
	 */
	public function get_fulfillment_data( \WC_Order_Item $item ): ?array {
		$fulfillment_meta = $item->get_meta( self::FULFILLMENT_DATA_META_KEY, true );
		if ( empty( $fulfillment_meta ) ) {
			return null;
		}

		return array_map( fn( $data ) => new Fulfillment( $data ), $fulfillment_meta );
	}

	/**
	 * Get fulfillment status for an order.
	 *
	 * @param \WC_Abstract_Order $order
	 * @param Fulfillment|null $fulfillment_data (Optional) Fulfillment data to avoid fetching it again.
	 *
	 * @return string
	 */
	public function get_order_fulfillment_status( \WC_Abstract_Order $order, ?Fulfillment $fulfillment_data = null ): string {
		$shipped_quantity = 0;
		$total_quantity   = 0;

		foreach ( $order->get_items() as $item ) {
			$item_quantity  = (int) $item->get_quantity();
			$total_quantity += $item_quantity;

			$fulfillment_data = $fulfillment_data ?? $this->get_fulfillment_data( $item );
			if ( empty( $fulfillment_data ) ) {
				continue;
			}

			foreach ( $fulfillment_data as $fulfillment ) {
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
}
