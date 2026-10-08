<?php

namespace WPO\OTD\Tests\Integration\Admin;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPO\OTD\Admin\OrderEdit\Screen;
use WPO\OTD\Core\Container\Container;
use WPO\OTD\Enums\FulfillmentStatuses;
use WPO\OTD\Services\FulfillmentService;

/**
 * The wpo_otd_save_fulfillment AJAX action.
 *
 * Fulfillments live in WooCommerce item meta and never open a plugin transaction, so WordPress's
 * own per-test rollback is enough here.
 */
class OrderEditAjaxTest extends WP_Ajax_UnitTestCase {
	private FulfillmentService $fulfillments;
	private \WC_Order $order;
	private \WC_Order_Item_Product $item;

	public function set_up() {
		parent::set_up();

		$container          = new Container();
		$this->fulfillments = $container->get( FulfillmentService::class );
		$container->get( Screen::class )->register_hooks();

		$product = new \WC_Product_Simple();
		$product->set_name( 'Lamp' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->order = wc_create_order();
		$this->order->add_product( $product, 3 );
		$this->order->save();

		$items      = $this->order->get_items();
		$this->item = reset( $items );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
	}

	public function test_saves_the_quantity_and_returns_the_new_tag(): void {
		$response = $this->save( array( 'quantity' => 2 ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, $response['data']['fulfillment_id'] );
		$this->assertSame( '<span class="wpo-otd-tag partially-fulfilled">2 / 3 fulfilled</span>', $response['data']['html'] );
		$this->assertSame( 2, $this->fulfillments->get_order_item_fulfillment_data( $this->item->get_id() )[0]->quantity );
		$this->assertSame(
			FulfillmentStatuses::PARTIALLY_FULFILLED,
			wc_get_order( $this->order->get_id() )->get_meta( FulfillmentService::ORDER_FULFILLMENT_STATUS_META_KEY )
		);
		$this->assertStringContainsString( 'Fulfillment updated for &quot;Lamp&quot;: 2 of 3 fulfilled.', $this->latest_note() );
	}

	public function test_updates_an_existing_fulfillment(): void {
		$this->save( array( 'quantity' => 1 ) );

		$response = $this->save( array( 'quantity' => 3, 'fulfillment_id' => 1 ) );

		$this->assertSame( 1, $response['data']['fulfillment_id'] );
		$this->assertStringContainsString( 'fully-fulfilled', $response['data']['html'] );
		$this->assertCount( 1, $this->fulfillments->get_order_item_fulfillment_data( $this->item->get_id() ) );
	}

	/**
	 * @dataProvider rejected_request_provider
	 */
	public function test_rejected_requests( array $overrides, ?string $role, string $message ): void {
		if ( $role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
		}

		$response = $this->save( $overrides );

		$this->assertFalse( $response['success'] );
		$this->assertSame( $message, $response['data']['message'] );
		$this->assertNull( $this->fulfillments->get_order_item_fulfillment_data( $this->item->get_id() ) );
	}

	public function rejected_request_provider(): array {
		return array(
			'missing nonce'     => array( array( 'nonce' => null ), null, 'Security verification failed.' ),
			'wrong nonce'       => array( array( 'nonce' => 'nope' ), null, 'Security verification failed.' ),
			'without the cap'   => array( array(), 'customer', 'You do not have permission to perform this action.' ),
			'missing item'      => array( array( 'item_id' => null ), null, 'Invalid item ID.' ),
			'unknown item'      => array( array( 'item_id' => 999999 ), null, 'Order item not found.' ),
			'too many'          => array( array( 'quantity' => 4 ), null, 'Fulfillment quantity cannot exceed 3.' ),
		);
	}

	/**
	 * Call the AJAX action and decode its JSON response.
	 *
	 * Entries set to null in $overrides are left out of the request.
	 *
	 * @param array $overrides
	 *
	 * @return array
	 */
	private function save( array $overrides ): array {
		$this->_last_response = '';

		$_POST = array_filter(
			array_merge(
				array(
					'nonce'    => wp_create_nonce( 'wpo_otd_order_edit' ),
					'item_id'  => $this->item->get_id(),
					'quantity' => 1,
				),
				$overrides
			),
			static fn( $value ) => null !== $value
		);

		try {
			$this->_handleAjax( 'wpo_otd_save_fulfillment' );
		} catch ( WPAjaxDieContinueException $e ) {
			// wp_send_json_*() ends the request.
			unset( $e );
		}

		return json_decode( $this->_last_response, true );
	}

	/**
	 * The content of the order's newest note.
	 *
	 * @return string
	 */
	private function latest_note(): string {
		$notes = wc_get_order_notes( array( 'order_id' => $this->order->get_id(), 'limit' => 1 ) );

		return $notes ? $notes[0]->content : '';
	}
}
