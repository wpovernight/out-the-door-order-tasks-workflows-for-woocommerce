<?php

namespace WPO\OTD\Tests\Integration\REST;

use WPO\OTD\Enums\FulfillmentStatuses;
use WPO\OTD\Services\FulfillmentService;

class FulfillmentControllerTest extends RestTestCase {
	private FulfillmentService $fulfillments;

	public function set_up() {
		parent::set_up();

		$this->fulfillments = $this->make( FulfillmentService::class );
	}

	public function test_guests_are_turned_away(): void {
		wp_set_current_user( 0 );

		$this->assertResponse( 401, $this->request( 'GET', '/fulfillments/orders' ), 'rest_forbidden' );
	}

	public function test_an_invalid_status_is_rejected(): void {
		$this->assertResponse( 400, $this->request( 'GET', '/fulfillments/orders', null, array( 'status' => 'shipped' ) ), 'invalid_status' );
	}

	public function test_orders_are_formatted_with_their_items(): void {
		$order = $this->create_order( array( 3, 1 ) );
		$order->set_billing_city( 'Sacramento' );
		$order->set_billing_state( 'CA' );
		$order->set_billing_country( 'US' );
		$order->set_shipping_city( 'Amsterdam' );
		$order->set_shipping_country( 'NL' );
		$order->save();

		$items = array_values( $order->get_items() );
		$this->fulfillments->save_order_item_fulfillment_quantity( $items[0]->get_id(), 2, null );
		$this->fulfillments->update_order_fulfillment_status_meta( $order->get_id() );

		$data = $this->json( $this->request( 'GET', '/fulfillments/orders' ) );

		$this->assertArrayNotHasKey( 'meta', $data, 'No pagination was asked for.' );
		$this->assertCount( 1, $data['data'] );

		$formatted = $data['data'][0];
		$this->assertSame( $order->get_id(), $formatted['order_id'] );
		$this->assertSame( $order->get_edit_order_url(), $formatted['order_url'] );
		$this->assertSame( FulfillmentStatuses::PARTIALLY_FULFILLED, $formatted['fulfillment_status'] );
		$this->assertSame( 'Jane Doe', $formatted['customer_name'] );
		$this->assertSame(
			array( 'city' => 'Amsterdam', 'state' => 'CA', 'country' => 'Netherlands' ),
			$formatted['customer_location'],
			'Shipping wins over billing for each part; the state stays a code when the country has no such state.'
		);

		$this->assertSame(
			array(
				array( 'name' => 'Product 1', 'quantity' => 3, 'fulfilled_quantity' => 2, 'fulfillment_status' => FulfillmentStatuses::PARTIALLY_FULFILLED ),
				array( 'name' => 'Product 2', 'quantity' => 1, 'fulfilled_quantity' => 0, 'fulfillment_status' => FulfillmentStatuses::NOT_FULFILLED ),
			),
			array_map(
				static fn( $item ) => array_intersect_key( $item, array_flip( array( 'name', 'quantity', 'fulfilled_quantity', 'fulfillment_status' ) ) ),
				$formatted['items']
			)
		);
		$this->assertSame( $items[0]->get_id(), $formatted['items'][0]['item_id'] );
		$this->assertSame( wc_placeholder_img_src( 'thumbnail' ), $formatted['items'][0]['image_url'] );
		$this->assertSame( array(), $formatted['items'][0]['attributes'] );
	}

	public function test_state_names_are_resolved_when_the_country_has_them(): void {
		$order = $this->create_order();
		$order->set_shipping_country( 'US' );
		$order->set_shipping_state( 'CA' );
		$order->save();
		$this->fulfillments->update_order_fulfillment_status_meta( $order->get_id() );

		$location = $this->json( $this->request( 'GET', '/fulfillments/orders' ) )['data'][0]['customer_location'];

		$this->assertSame( 'California', $location['state'] );
		$this->assertSame( 'United States (US)', $location['country'] );
	}

	public function test_variation_attributes_are_listed(): void {
		$product = new \WC_Product_Variable();
		$product->set_name( 'Shirt' );
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'Large' ) );
		$attribute->set_variation( true );
		$product->set_attributes( array( $attribute ) );
		$product->save();

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $product->get_id() );
		$variation->set_attributes( array( 'size' => 'Large' ) );
		$variation->set_regular_price( '10' );
		$variation->save();

		$order = wc_create_order();
		$order->add_product( wc_get_product( $variation->get_id() ), 1 );
		$order->save();
		$this->fulfillments->update_order_fulfillment_status_meta( $order->get_id() );

		$item = $this->json( $this->request( 'GET', '/fulfillments/orders' ) )['data'][0]['items'][0];

		$this->assertSame( array( array( 'label' => 'Size', 'value' => 'Large' ) ), $item['attributes'] );
	}

	public function test_global_attributes_use_term_names_and_images_use_the_thumbnail(): void {
		$attribute_id = wc_create_attribute( array( 'name' => 'Color', 'slug' => 'color' ) );
		register_taxonomy( 'pa_color', array( 'product' ) );
		$blue = wp_insert_term( 'Navy Blue', 'pa_color', array( 'slug' => 'navy-blue' ) );

		$product   = new \WC_Product_Variable();
		$attribute = new \WC_Product_Attribute();
		$attribute->set_id( $attribute_id );
		$attribute->set_name( 'pa_color' );
		$attribute->set_options( array( $blue['term_id'] ) );
		$attribute->set_variation( true );
		$product->set_name( 'Shirt' );
		$product->set_attributes( array( $attribute ) );
		$product->save();

		// An attachment record is enough for a URL; no file is written to the uploads folder.
		$image_id = self::factory()->attachment->create(
			array(
				'file'           => 'shirt.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $product->get_id() );
		$variation->set_attributes( array( 'pa_color' => 'navy-blue' ) );
		$variation->set_image_id( $image_id );
		$variation->set_regular_price( '10' );
		$variation->save();

		$order = wc_create_order();
		$order->add_product( wc_get_product( $variation->get_id() ), 1 );
		$order->save();
		$this->fulfillments->update_order_fulfillment_status_meta( $order->get_id() );

		$item  = $this->json( $this->request( 'GET', '/fulfillments/orders' ) )['data'][0]['items'][0];
		$label = wc_attribute_label( 'pa_color', wc_get_product( $variation->get_id() ) );

		unregister_taxonomy( 'pa_color' );

		// The label is WooCommerce's; the value is resolved from the term slug to its name.
		$this->assertSame( array( array( 'label' => $label, 'value' => 'Navy Blue' ) ), $item['attributes'] );
		$this->assertStringEndsWith( '/shirt.jpg', $item['image_url'] );
	}

	public function test_orders_can_be_filtered_by_status_and_paginated(): void {
		$fulfilled = $this->order_with( 1, 1 );
		$this->order_with( 2, 1 );
		$this->order_with( 1, 0 );

		$only = $this->json( $this->request( 'GET', '/fulfillments/orders', null, array( 'status' => FulfillmentStatuses::FULFILLED ) ) )['data'];
		$this->assertSame( array( $fulfilled ), array_column( $only, 'order_id' ) );

		$page = $this->json( $this->request( 'GET', '/fulfillments/orders', null, array( 'per_page' => 2, 'page' => 2 ) ) );
		$this->assertCount( 1, $page['data'] );
		$this->assertSame(
			array(
				'current_page' => 2,
				'per_page'     => 2,
				'last_page'    => 2,
				'total'        => 3,
				'from'         => 3,
				'to'           => 3,
			),
			$page['meta']
		);
	}

	public function test_invalid_pagination_params_are_rejected(): void {
		$this->assertResponse( 400, $this->request( 'GET', '/fulfillments/orders', null, array( 'page' => 0 ) ), 'rest_invalid_param' );
	}

	public function test_the_response_can_be_filtered(): void {
		$this->order_with( 1, 1 );
		add_filter(
			'wpo_otd_rest_get_fulfillment_orders',
			static fn( $data, $status ) => array( array( 'status' => $status, 'count' => count( $data ) ) ),
			10,
			2
		);

		$this->assertSame(
			array( array( 'status' => 'fulfilled', 'count' => 1 ) ),
			$this->json( $this->request( 'GET', '/fulfillments/orders', null, array( 'status' => 'fulfilled' ) ) )['data']
		);
	}

	/**
	 * Create a one-line order, ship some of it, and cache its status.
	 *
	 * @param int $ordered
	 * @param int $shipped
	 *
	 * @return int The order ID.
	 */
	private function order_with( int $ordered, int $shipped ): int {
		$order = $this->create_order( array( $ordered ) );
		$items = $order->get_items();

		if ( $shipped ) {
			$this->fulfillments->save_order_item_fulfillment_quantity( reset( $items )->get_id(), $shipped, null );
		}

		$this->fulfillments->update_order_fulfillment_status_meta( $order->get_id() );

		return $order->get_id();
	}
}
