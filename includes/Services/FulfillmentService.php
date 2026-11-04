<?php

namespace WPO\OTD\Services;

use WPO\OTD\Core\Logger;
use WPO\OTD\Enums\FulfillmentStatuses;
use WPO\OTD\Models\Fulfillment;
use WPO\OTD\Utilities\Paginator;

defined( 'ABSPATH' ) || exit;

final class FulfillmentService {
	public const FULFILLMENT_DATA_META_KEY            = '_wpo_otd_fulfillment_data';
	public const ORDER_FULFILLMENT_STATUS_META_KEY   = '_wpo_otd_fulfillment_status';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Refunded units need no fulfilment, so a refund can change an order's status.
		add_action( 'woocommerce_order_refunded', array( $this, 'refresh_after_refund' ) );
		add_action( 'woocommerce_refund_deleted', array( $this, 'refresh_after_refund_deleted' ), 10, 2 );
	}

	/**
	 * Recalculate the cached status of a tracked order after a refund.
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	public function refresh_after_refund( int $order_id ): void {
		$this->refresh_tracked_order( $order_id );
	}

	/**
	 * Recalculate the cached status of a tracked order after one of its refunds was deleted.
	 *
	 * @param int $refund_id
	 * @param int $order_id
	 *
	 * @return void
	 */
	public function refresh_after_refund_deleted( int $refund_id, int $order_id ): void {
		$this->refresh_tracked_order( $order_id );
	}

	/**
	 * Get fulfillment data for an order item.
	 *
	 * @param int|\WC_Order_Item|\WC_Order_Item_Product $item
	 *
	 * @return Fulfillment[]|null
	 */
	public function get_order_item_fulfillment_data( $item ): ?array {
		if ( is_numeric( $item ) ) {
			if ( (int) $item <= 0 ) {
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
	 * Get fulfillment status for an order: fulfilled when every item is, not fulfilled when no item is fulfilled at
	 * all, partially fulfilled otherwise. Each item is measured against its quantity to fulfil, refunds taken off. An
	 * item with nothing to fulfil is left out, and an order none of whose items has anything to fulfil is not
	 * fulfilled, as an order without items is.
	 *
	 * @param \WC_Abstract_Order $order
	 *
	 * @return string
	 */
	public function get_order_fulfillment_status( \WC_Abstract_Order $order ): string {
		$statuses = array();

		foreach ( $order->get_items() as $item ) {
			$ordered   = (int) $item->get_quantity();
			$quantity  = $this->get_quantity_to_fulfil( $item );
			$fulfilled = $this->get_fulfilled_quantity( $item );

			if ( $fulfilled > $ordered ) {
				Logger::warning(
					sprintf(
						'Fulfillment quantity (%d) exceeds the ordered quantity (%d) of item #%d in order #%d.',
						$fulfilled,
						$ordered,
						$item->get_id(),
						$order->get_id()
					)
				);
			}

			if ( $quantity > 0 ) {
				$statuses[ $this->get_item_fulfillment_status( $fulfilled, $quantity ) ] = true;
			}
		}

		$statuses = array_keys( $statuses );

		if ( array() === $statuses || array( FulfillmentStatuses::NOT_FULFILLED ) === $statuses ) {
			return FulfillmentStatuses::NOT_FULFILLED;
		}

		return array( FulfillmentStatuses::FULFILLED ) === $statuses
			? FulfillmentStatuses::FULFILLED
			: FulfillmentStatuses::PARTIALLY_FULFILLED;
	}

	/**
	 * Get how many units of an order item are to be fulfilled: its quantity less the refunded units, never below 0.
	 *
	 * @param \WC_Order_Item $item
	 * @param int|null       $quantity The item's quantity, when it is about to change; default the saved one.
	 *
	 * @return int
	 */
	public function get_quantity_to_fulfil( \WC_Order_Item $item, ?int $quantity = null ): int {
		$quantity ??= (int) $item->get_quantity();
		$order      = $item->get_order();

		// WooCommerce reports refunded units as a negative number.
		if ( $order instanceof \WC_Abstract_Order ) {
			$quantity += (int) $order->get_qty_refunded_for_item( $item->get_id() );
		}

		return max( 0, $quantity );
	}

	/**
	 * Get how many units of an order item are fulfilled, summed across all its fulfillment entries.
	 *
	 * @param int|\WC_Order_Item $item Order item or its ID.
	 *
	 * @return int
	 */
	public function get_fulfilled_quantity( int|\WC_Order_Item $item ): int {
		$fulfilled = 0;

		foreach ( $this->get_order_item_fulfillment_data( $item ) ?? array() as $fulfillment ) {
			$fulfilled += (int) $fulfillment->quantity;
		}

		return $fulfilled;
	}

	/**
	 * Get the fulfillment status of an order item: fulfilled once the fulfilled quantity reaches the item's quantity,
	 * going past it included.
	 *
	 * @param int $fulfilled_quantity Units fulfilled, summed across the item's entries.
	 * @param int $quantity           Units to fulfil.
	 *
	 * @return string
	 */
	public function get_item_fulfillment_status( int $fulfilled_quantity, int $quantity ): string {
		if ( $fulfilled_quantity >= $quantity ) {
			return FulfillmentStatuses::FULFILLED;
		}

		return $fulfilled_quantity > 0 ? FulfillmentStatuses::PARTIALLY_FULFILLED : FulfillmentStatuses::NOT_FULFILLED;
	}

	/**
	 * Save fulfillment quantity for an order item.
	 *
	 * @param int      $item_id
	 * @param int      $quantity
	 * @param int|null $fulfillment_id
	 *
	 * @return int|null The id of the saved fulfillment entry, or null on failure.
	 */
	public function save_order_item_fulfillment_quantity( int $item_id, int $quantity, ?int $fulfillment_id ): ?int {
		$fulfillment_data = $this->get_order_item_fulfillment_data( $item_id ) ?? array();

		$saved_id = null;
		foreach ( $fulfillment_data as $index => $fulfillment ) {
			if ( $fulfillment->id === $fulfillment_id ) {
				$fulfillment->quantity      = $quantity;
				$fulfillment_data[ $index ] = $fulfillment;
				$saved_id                   = $fulfillment->id;
				break;
			}
		}

		// If not updated, create a new fulfillment entry.
		if ( null === $saved_id ) {
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
			$saved_id           = $new_id;
		}

		$fulfillment_data_array = array_map( fn( $f ) => $f->to_array(), $fulfillment_data );

		$existing = wc_get_order_item_meta( $item_id, self::FULFILLMENT_DATA_META_KEY, true );
		if ( $existing === $fulfillment_data_array ) {
			return $saved_id;
		}

		$result = (bool) wc_update_order_item_meta( $item_id, self::FULFILLMENT_DATA_META_KEY, $fulfillment_data_array );

		if ( $result ) {
			/**
			 * Fires after a fulfillment quantity has been saved for an order item.
			 *
			 * @param int $item_id  The order item ID.
			 * @param int $quantity The fulfillment quantity that was saved.
			 */
			do_action( 'wpo_otd_fulfillment_quantity_saved', $item_id, $quantity );
		}

		return $result ? $saved_id : null;
	}

	/**
	 * Delete fulfillment data for an order item.
	 *
	 * @param int $item_id
	 *
	 * @return bool
	 */
	public function delete_order_item_fulfillment_data( int $item_id ): bool {
		$result = wc_delete_order_item_meta( $item_id, self::FULFILLMENT_DATA_META_KEY );

		if ( $result ) {
			/**
			 * Fires after fulfillment data has been deleted for an order item.
			 *
			 * @param int $item_id The order item ID.
			 */
			do_action( 'wpo_otd_fulfillment_data_deleted', $item_id );
		}

		return $result;
	}

	/**
	 * Recalculate and persist the order-level fulfillment status as order meta.
	 *
	 * This is a denormalized cache that enables efficient queries.
	 *
	 * @param \WC_Abstract_Order|int $order Order object or order ID.
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

		$old_status = $order->get_meta( self::ORDER_FULFILLMENT_STATUS_META_KEY );
		$status     = $this->get_order_fulfillment_status( $order );

		$order->update_meta_data( self::ORDER_FULFILLMENT_STATUS_META_KEY, $status );
		$order->save_meta_data();

		if ( $status !== $old_status ) {
			/**
			 * Fires after the order-level fulfillment status has changed.
			 *
			 * @param \WC_Abstract_Order $order      The order whose status changed.
			 * @param string             $status     The new fulfillment status.
			 * @param string             $old_status The previous fulfillment status.
			 */
			do_action( 'wpo_otd_order_fulfillment_status_changed', $order, $status, $old_status );
		}

		return $status;
	}

	/**
	 * Recalculate an order's cached status, when it has one or any of its items has fulfillment entries. Other orders
	 * stay out of the fulfillment lists.
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	private function refresh_tracked_order( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Abstract_Order ) {
			return;
		}

		if ( '' === $order->get_meta( self::ORDER_FULFILLMENT_STATUS_META_KEY ) && ! $this->has_fulfillment_entries( $order ) ) {
			return;
		}

		$this->update_order_fulfillment_status_meta( $order );
	}

	/**
	 * Whether any item of an order has fulfillment entries.
	 *
	 * @param \WC_Abstract_Order $order
	 *
	 * @return bool
	 */
	private function has_fulfillment_entries( \WC_Abstract_Order $order ): bool {
		foreach ( $order->get_items() as $item ) {
			if ( null !== $this->get_order_item_fulfillment_data( $item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove all fulfillment data: per-item data meta and the order-level status meta.
	 *
	 * @return int Number of orders cleared.
	 */
	public function clear_all(): int {
		$orders = $this->get_orders_by_fulfillment_status()->items();
		$count  = 0;

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$this->delete_order_item_fulfillment_data( $item->get_id() );
			}

			$order->delete_meta_data( self::ORDER_FULFILLMENT_STATUS_META_KEY );
			$order->save();

			$count++;
		}

		return $count;
	}

	/**
	 * Query orders by their cached fulfillment status.
	 *
	 * @param string   $status   One of FulfillmentStatuses constants, or empty for all.
	 * @param array    $args     Additional wc_get_orders() arguments.
	 * @param int|null $page     Page number (1-based), or null for the full list.
	 * @param int|null $per_page Orders per page (required when $page is provided).
	 *
	 * @return Paginator
	 */
	public function get_orders_by_fulfillment_status(
		string $status = '',
		array $args = array(),
		?int $page = null,
		?int $per_page = null
	): Paginator {
		if ( ! empty( $status ) && ! FulfillmentStatuses::is_valid( $status ) ) {
			return Paginator::full( array() );
		}

		$query_args = $this->build_status_query_args( $status, $args );

		// No pagination requested: return the full list as a single page.
		if ( null === $page ) {
			return Paginator::full( wc_get_orders( $query_args ) );
		}

		$page     = max( 1, $page );
		$per_page = max( 1, (int) $per_page );

		$query_args = array_merge(
			$query_args,
			array(
				'paginate' => true,
				'limit'    => $per_page,
				'paged'    => $page,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);

		$result = wc_get_orders( $query_args );

		return new Paginator( $result->orders, (int) $result->total, $per_page, $page );
	}

	/**
	 * Build the shared wc_get_orders() arguments for fulfillment-status queries.
	 *
	 * @param string $status One of FulfillmentStatuses constants, or empty for all.
	 * @param array  $args   Additional wc_get_orders() arguments.
	 *
	 * @return array<string,mixed>
	 */
	private function build_status_query_args( string $status, array $args ): array {
		/**
		 * Filter the number of days to look back when querying orders by fulfillment status. The value is cast to a
		 * whole number, and 0 or less sets no limit.
		 *
		 * @param int $days Number of days. Default 0 (no date limit).
		 */
		$days = (int) apply_filters( 'wpo_otd_fulfillment_status_query_days', 0 );

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
		return apply_filters( 'wpo_otd_fulfillment_status_query_args', wp_parse_args( $args, $defaults ), $status );
	}
}
