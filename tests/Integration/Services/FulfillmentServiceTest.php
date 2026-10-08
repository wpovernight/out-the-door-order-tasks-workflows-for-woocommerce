<?php

namespace WPO\OTD\Tests\Integration\Services;

use WPO\OTD\Enums\FulfillmentStatuses;
use WPO\OTD\Models\Fulfillment;
use WPO\OTD\Services\FulfillmentService;
use WPO\OTD\Tests\IntegrationTestCase;

class FulfillmentServiceTest extends IntegrationTestCase {
	private FulfillmentService $service;

	public function set_up() {
		parent::set_up();

		$this->service = $this->make( FulfillmentService::class );
	}

	/** ================================
	 *   Item data
	 *  ================================ */

	public function test_an_item_without_fulfillments_has_no_data(): void {
		$item = $this->first_item( $this->create_order() );

		$this->assertNull( $this->service->get_order_item_fulfillment_data( $item ) );
		$this->assertNull( $this->service->get_order_item_fulfillment_data( $item->get_id() ) );
	}

	public function test_item_data_can_be_read_by_id_or_by_item(): void {
		$item = $this->first_item( $this->create_order( array( 3 ) ) );
		$this->service->save_order_item_fulfillment_quantity( $item->get_id(), 2, null );

		$by_id   = $this->service->get_order_item_fulfillment_data( $item->get_id() );
		$by_item = $this->service->get_order_item_fulfillment_data( \WC_Order_Factory::get_order_item( $item->get_id() ) );

		$this->assertEquals( array( new Fulfillment( array( 'id' => 1, 'quantity' => 2 ) ) ), $by_id );
		$this->assertEquals( $by_id, $by_item );
	}

	/**
	 * @dataProvider invalid_item_provider
	 */
	public function test_invalid_items_have_no_data( $item ): void {
		$this->assertNull( $this->service->get_order_item_fulfillment_data( $item ) );
	}

	public function invalid_item_provider(): array {
		return array(
			'zero'     => array( 0 ),
			'negative' => array( -3 ),
			'string'   => array( 'abc' ),
			'object'   => array( new \stdClass() ),
		);
	}

	public function test_corrupted_meta_is_ignored(): void {
		$item = $this->first_item( $this->create_order() );
		wc_update_order_item_meta( $item->get_id(), FulfillmentService::FULFILLMENT_DATA_META_KEY, 'not an array' );

		$this->assertNull( $this->service->get_order_item_fulfillment_data( $item->get_id() ) );
	}

	/** ================================
	 *   save_order_item_fulfillment_quantity()
	 *  ================================ */

	public function test_saving_creates_then_updates_one_entry(): void {
		$item  = $this->first_item( $this->create_order( array( 5 ) ) );
		$saved = $this->capture( 'wpo_otd_fulfillment_quantity_saved', 2 );

		$this->assertSame( 1, $this->service->save_order_item_fulfillment_quantity( $item->get_id(), 2, null ) );
		$this->assertSame( 1, $this->service->save_order_item_fulfillment_quantity( $item->get_id(), 4, 1 ) );

		$this->assertSame(
			array( array( 'id' => 1, 'quantity' => 4 ) ),
			wc_get_order_item_meta( $item->get_id(), FulfillmentService::FULFILLMENT_DATA_META_KEY, true )
		);
		$this->assertSame( array( array( $item->get_id(), 2 ), array( $item->get_id(), 4 ) ), $saved() );
	}

	public function test_saving_an_unknown_entry_adds_the_next_id(): void {
		$item = $this->first_item( $this->create_order( array( 5 ) ) );
		$this->service->save_order_item_fulfillment_quantity( $item->get_id(), 1, null );

		$this->assertSame( 2, $this->service->save_order_item_fulfillment_quantity( $item->get_id(), 3, 7 ) );
		$this->assertSame( array( 1, 3 ), array_map( static fn( $f ) => $f->quantity, $this->service->get_order_item_fulfillment_data( $item->get_id() ) ) );
	}

	public function test_saving_the_same_quantity_fires_nothing(): void {
		$item = $this->first_item( $this->create_order( array( 5 ) ) );
		$this->service->save_order_item_fulfillment_quantity( $item->get_id(), 2, null );

		$this->assertSame( 1, $this->service->save_order_item_fulfillment_quantity( $item->get_id(), 2, 1 ) );
		$this->assertSame( 1, did_action( 'wpo_otd_fulfillment_quantity_saved' ) );
	}

	public function test_deleting_item_data(): void {
		$item = $this->first_item( $this->create_order() );

		$this->assertFalse( $this->service->delete_order_item_fulfillment_data( $item->get_id() ), 'Nothing to delete.' );

		$this->service->save_order_item_fulfillment_quantity( $item->get_id(), 1, null );

		$this->assertTrue( $this->service->delete_order_item_fulfillment_data( $item->get_id() ) );
		$this->assertNull( $this->service->get_order_item_fulfillment_data( $item->get_id() ) );
		$this->assertSame( 1, did_action( 'wpo_otd_fulfillment_data_deleted' ) );
	}

	/** ================================
	 *   Statuses
	 *  ================================ */

	/**
	 * @dataProvider order_status_provider
	 */
	public function test_order_status_compares_shipped_and_ordered_quantities( array $ordered, array $shipped, string $expected ): void {
		$order = $this->create_order( $ordered );

		foreach ( array_values( $order->get_items() ) as $index => $item ) {
			if ( isset( $shipped[ $index ] ) ) {
				$this->service->save_order_item_fulfillment_quantity( $item->get_id(), $shipped[ $index ], null );
			}
		}

		$this->assertSame( $expected, $this->service->get_order_fulfillment_status( wc_get_order( $order->get_id() ) ) );
	}

	public function order_status_provider(): array {
		return array(
			'nothing shipped'      => array( array( 2, 1 ), array(), FulfillmentStatuses::NOT_FULFILLED ),
			'zero shipped'         => array( array( 2 ), array( 0 ), FulfillmentStatuses::NOT_FULFILLED ),
			'some shipped'         => array( array( 2, 1 ), array( 1 ), FulfillmentStatuses::PARTIALLY_FULFILLED ),
			'one line complete'    => array( array( 2, 1 ), array( 2 ), FulfillmentStatuses::PARTIALLY_FULFILLED ),
			'everything shipped'   => array( array( 2, 1 ), array( 2, 1 ), FulfillmentStatuses::FULFILLED ),
			'more than ordered'    => array( array( 1 ), array( 3 ), FulfillmentStatuses::FULFILLED ),
		);
	}

	public function test_an_order_without_items_is_not_fulfilled(): void {
		$this->assertSame( FulfillmentStatuses::NOT_FULFILLED, $this->service->get_order_fulfillment_status( wc_create_order() ) );
	}

	public function test_item_status(): void {
		$item = $this->first_item( $this->create_order( array( 3 ) ) );

		$this->assertSame( FulfillmentStatuses::NOT_FULFILLED, $this->service->get_order_item_fulfillment_status( $item, new Fulfillment( array( 'quantity' => 0 ) ) ) );
		$this->assertSame( FulfillmentStatuses::PARTIALLY_FULFILLED, $this->service->get_order_item_fulfillment_status( $item, new Fulfillment( array( 'quantity' => 2 ) ) ) );
		$this->assertSame( FulfillmentStatuses::FULFILLED, $this->service->get_order_item_fulfillment_status( $item, new Fulfillment( array( 'quantity' => 3 ) ) ) );
	}

	public function test_updating_the_status_meta_stores_it_and_reports_changes(): void {
		$order   = $this->create_order( array( 2 ) );
		$changes = $this->capture( 'wpo_otd_order_fulfillment_status_changed', 3 );

		$this->assertSame( FulfillmentStatuses::NOT_FULFILLED, $this->service->update_order_fulfillment_status_meta( $order->get_id() ) );
		$this->assertSame( FulfillmentStatuses::NOT_FULFILLED, $this->service->update_order_fulfillment_status_meta( wc_get_order( $order->get_id() ) ), 'Unchanged.' );

		$this->service->save_order_item_fulfillment_quantity( $this->first_item( $order )->get_id(), 2, null );
		$this->assertSame( FulfillmentStatuses::FULFILLED, $this->service->update_order_fulfillment_status_meta( $order->get_id() ) );

		$this->assertSame( FulfillmentStatuses::FULFILLED, wc_get_order( $order->get_id() )->get_meta( FulfillmentService::ORDER_FULFILLMENT_STATUS_META_KEY ) );
		$this->assertSame(
			array(
				array( FulfillmentStatuses::NOT_FULFILLED, '' ),
				array( FulfillmentStatuses::FULFILLED, FulfillmentStatuses::NOT_FULFILLED ),
			),
			array_map( static fn( $args ) => array( $args[1], $args[2] ), $changes() )
		);
	}

	public function test_updating_the_status_meta_of_a_missing_order(): void {
		$this->assertSame( '', $this->service->update_order_fulfillment_status_meta( 999999 ) );
	}

	/** ================================
	 *   Querying and clearing
	 *  ================================ */

	public function test_orders_are_queried_by_their_cached_status(): void {
		$fulfilled   = $this->order_with_status( array( 1 ), array( 1 ) );
		$partial     = $this->order_with_status( array( 2 ), array( 1 ) );
		$unfulfilled = $this->order_with_status( array( 1 ), array() );
		$this->create_order(); // Never had its status cached.

		$this->assertSame( array( $fulfilled ), $this->order_ids( $this->service->get_orders_by_fulfillment_status( FulfillmentStatuses::FULFILLED )->items() ) );
		$this->assertSame( array( $partial ), $this->order_ids( $this->service->get_orders_by_fulfillment_status( FulfillmentStatuses::PARTIALLY_FULFILLED )->items() ) );
		$this->assertEqualsCanonicalizing(
			array( $fulfilled, $partial, $unfulfilled ),
			$this->order_ids( $this->service->get_orders_by_fulfillment_status()->items() )
		);
	}

	public function test_an_invalid_status_matches_nothing(): void {
		$this->order_with_status( array( 1 ), array( 1 ) );

		$this->assertSame( array(), $this->service->get_orders_by_fulfillment_status( 'shipped' )->items() );
	}

	public function test_orders_can_be_paginated_newest_first(): void {
		$ids = array();
		foreach ( array( '2025-01-01', '2025-02-01', '2025-03-01' ) as $date ) {
			$ids[ $date ] = $this->order_with_status( array( 1 ), array(), $date );
		}

		$page = $this->service->get_orders_by_fulfillment_status( '', array(), 2, 2 );

		$this->assertSame( array( $ids['2025-01-01'] ), $this->order_ids( $page->items() ) );
		$this->assertSame( 3, $page->total() );
		$this->assertSame( 2, $page->current_page() );
		$this->assertSame( 2, $page->last_page() );
	}

	public function test_the_query_can_be_limited_by_age_and_filtered(): void {
		$recent = $this->order_with_status( array( 1 ), array() );
		$this->order_with_status( array( 1 ), array(), '-40 days' );

		add_filter( 'wpo_otd_fulfillment_status_query_days', static fn() => 30 );
		$this->assertSame( array( $recent ), $this->order_ids( $this->service->get_orders_by_fulfillment_status()->items() ) );

		add_filter(
			'wpo_otd_fulfillment_status_query_args',
			static function ( $args, $status ) {
				// Every test order is pending.
				$args['status'] = array( 'wc-completed' );

				return $args;
			},
			10,
			2
		);
		$this->assertSame( array(), $this->service->get_orders_by_fulfillment_status()->items() );
	}

	public function test_clear_all_removes_item_data_and_cached_statuses(): void {
		$first  = $this->order_with_status( array( 2 ), array( 1 ) );
		$second = $this->order_with_status( array( 1 ), array( 1 ) );

		$this->assertSame( 2, $this->service->clear_all() );

		foreach ( array( $first, $second ) as $order_id ) {
			$order = wc_get_order( $order_id );
			$this->assertSame( '', $order->get_meta( FulfillmentService::ORDER_FULFILLMENT_STATUS_META_KEY ) );
			$this->assertNull( $this->service->get_order_item_fulfillment_data( $this->first_item( $order ) ) );
		}

		$this->assertSame( 0, $this->service->clear_all() );
	}

	/**
	 * Create an order, ship quantities per line, and cache its status.
	 *
	 * @param int[]       $ordered
	 * @param int[]       $shipped
	 * @param string|null $created
	 *
	 * @return int The order ID.
	 */
	private function order_with_status( array $ordered, array $shipped, ?string $created = null ): int {
		$order = $this->create_order( $ordered );

		if ( $created ) {
			$order->set_date_created( strtotime( $created ) );
			$order->save();
		}

		foreach ( array_values( $order->get_items() ) as $index => $item ) {
			if ( isset( $shipped[ $index ] ) ) {
				$this->service->save_order_item_fulfillment_quantity( $item->get_id(), $shipped[ $index ], null );
			}
		}

		$this->service->update_order_fulfillment_status_meta( $order->get_id() );

		return $order->get_id();
	}

	/**
	 * Get the first line item of an order.
	 *
	 * @param \WC_Abstract_Order $order
	 *
	 * @return \WC_Order_Item_Product
	 */
	private function first_item( \WC_Abstract_Order $order ): \WC_Order_Item_Product {
		$items = $order->get_items();

		return reset( $items );
	}

	/**
	 * Get the IDs of a list of orders.
	 *
	 * @param \WC_Abstract_Order[] $orders
	 *
	 * @return int[]
	 */
	private function order_ids( array $orders ): array {
		return array_map( static fn( $order ) => $order->get_id(), $orders );
	}

	/**
	 * Record the arguments of every call to an action.
	 *
	 * @param string $action
	 * @param int    $accepted_args
	 *
	 * @return callable Returns the recorded argument lists.
	 */
	private function capture( string $action, int $accepted_args ): callable {
		$calls = array();

		add_action(
			$action,
			static function ( ...$args ) use ( &$calls ) {
				$calls[] = $args;
			},
			10,
			$accepted_args
		);

		return static function () use ( &$calls ) {
			return $calls;
		};
	}
}
