<?php

namespace WPO\OTD\Tests\Integration\Admin;

use WPO\OTD\Admin\OrderEdit\Screen;
use WPO\OTD\Enums\FulfillmentStatuses;
use WPO\OTD\Services\FulfillmentService;
use WPO\OTD\Tests\IntegrationTestCase;

/**
 * The order edit screen: meta box, fulfillment column and saving. The AJAX handler is covered
 * in OrderEditAjaxTest.
 */
class OrderEditScreenTest extends IntegrationTestCase {
	private Screen $screen;
	private FulfillmentService $fulfillments;

	public function set_up() {
		parent::set_up();

		$this->screen       = $this->make( Screen::class );
		$this->fulfillments = $this->make( FulfillmentService::class );
	}

	public function tear_down() {
		$GLOBALS['wp_scripts']   = null;
		$GLOBALS['wp_styles']    = null;
		$GLOBALS['wp_meta_boxes'] = null;
		unset( $GLOBALS['theorder'] );

		parent::tear_down();
	}

	public function test_hooks_into_the_order_screen(): void {
		$this->screen->register_hooks();

		foreach (
			array(
				'add_meta_boxes'                       => 'add_meta_box',
				'woocommerce_admin_order_item_headers' => 'order_items_headers',
				'woocommerce_admin_order_item_values'  => 'order_items_values',
				'woocommerce_before_save_order_items'  => 'save_posted_fulfillment_quantities',
				'woocommerce_saved_order_items'        => 'refresh_order_fulfillment_status',
				'woocommerce_before_delete_order_item' => 'on_delete_order_item',
				'admin_enqueue_scripts'                => 'enqueue_scripts',
				'wp_ajax_wpo_otd_save_fulfillment'     => 'ajax_save_fulfillment',
			) as $hook => $method
		) {
			$this->assertNotFalse( has_action( $hook, array( $this->screen, $method ) ), "{$method} is not hooked to {$hook}." );
		}
	}

	/** ================================
	 *   Meta box
	 *  ================================ */

	public function test_adds_the_meta_box_to_orders_only(): void {
		global $wp_meta_boxes;

		$order_screen = wc_get_page_screen_id( 'shop-order' );

		$this->screen->add_meta_box( 'post', new \stdClass() );
		$this->assertEmpty( $wp_meta_boxes['post'] ?? array() );

		$this->screen->add_meta_box( $order_screen, new \stdClass() );
		$this->assertArrayHasKey( 'wpo-otd-order-meta-box', $wp_meta_boxes[ $order_screen ]['normal']['high'] );

		ob_start();
		$this->screen->render_meta_box();
		$this->assertSame( '<div id="wpo-otd-order-meta-box-content"></div>', ob_get_clean() );
	}

	/** ================================
	 *   Fulfillment column
	 *  ================================ */

	public function test_the_column_header(): void {
		ob_start();
		$this->screen->order_items_headers();

		$this->assertSame( '<th>Fulfillments</th>', ob_get_clean() );
	}

	public function test_the_column_shows_the_progress_and_an_editor_for_product_lines(): void {
		$order = $this->create_order( array( 3 ) );
		$item  = $this->first_item( $order );
		$this->fulfillments->save_order_item_fulfillment_quantity( $item->get_id(), 2, null );
		$item = \WC_Order_Factory::get_order_item( $item->get_id() );

		ob_start();
		$this->screen->order_items_values( $item->get_product(), $item, $item->get_id() );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<span class="wpo-otd-tag partially-fulfilled">2 / 3 fulfilled</span>', $html );
		$this->assertStringContainsString( 'name="wpo-otd-fulfillment-quantity[' . $item->get_id() . '][1]"', $html );
		$this->assertStringContainsString( 'max="3"', $html );
		$this->assertStringContainsString( 'value="2"', $html );
	}

	public function test_a_line_without_fulfillments_is_not_fulfilled(): void {
		$order = $this->create_order( array( 2 ) );
		$item  = $this->first_item( $order );

		ob_start();
		$this->screen->order_items_values( $item->get_product(), $item, $item->get_id() );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<span class="wpo-otd-tag not-fulfilled">0 / 2 fulfilled</span>', $html );
		$this->assertStringContainsString( 'name="wpo-otd-fulfillment-quantity[' . $item->get_id() . '][]"', $html );
	}

	public function test_refunded_units_are_taken_off_the_total(): void {
		$order = $this->create_order( array( 3 ) );
		$item  = $this->first_item( $order );
		wc_create_refund(
			array(
				'order_id'   => $order->get_id(),
				'amount'     => 10,
				'line_items' => array( $item->get_id() => array( 'qty' => 1, 'refund_total' => 10 ) ),
			)
		);

		ob_start();
		$this->screen->order_items_values( $item->get_product(), $item, $item->get_id() );

		$this->assertStringContainsString( '0 / 2 fulfilled', ob_get_clean() );
	}

	public function test_other_lines_get_no_column_content(): void {
		$order = $this->create_order();
		$fee   = new \WC_Order_Item_Fee();
		$fee->set_name( 'Fee' );
		$order->add_item( $fee );
		$order->save();

		ob_start();
		$this->screen->order_items_values( false, $fee, $fee->get_id() );

		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * @dataProvider status_html_provider
	 */
	public function test_status_tags( string $status, string $class ): void {
		$this->assertSame(
			"<span class=\"wpo-otd-tag {$class}\">1 / 4 fulfilled</span>",
			$this->screen->get_fulfillment_status_html( $status, 1, 4 )
		);
	}

	public function status_html_provider(): array {
		return array(
			'fulfilled'      => array( FulfillmentStatuses::FULFILLED, 'fully-fulfilled' ),
			'partially'      => array( FulfillmentStatuses::PARTIALLY_FULFILLED, 'partially-fulfilled' ),
			'not fulfilled'  => array( FulfillmentStatuses::NOT_FULFILLED, 'not-fulfilled' ),
			'unknown status' => array( 'other', 'not-fulfilled' ),
		);
	}

	/** ================================
	 *   Saving
	 *  ================================ */

	public function test_posted_quantities_are_saved_per_line(): void {
		$order = $this->create_order( array( 3, 2 ) );
		$items = array_values( $order->get_items() );
		$this->fulfillments->save_order_item_fulfillment_quantity( $items[1]->get_id(), 1, null );

		$this->screen->save_posted_fulfillment_quantities(
			$order->get_id(),
			array(
				// A line without a fulfillment posts "[item_id][]", which PHP parses as index 0.
				'wpo-otd-fulfillment-quantity' => array(
					$items[0]->get_id() => array( 0 => '2' ),
					$items[1]->get_id() => array( 1 => '2' ),
					0                   => array( 0 => '5' ),
					999999              => 'not an array',
				),
			)
		);

		$this->assertSame( 2, $this->fulfillments->get_order_item_fulfillment_data( $items[0]->get_id() )[0]->quantity );
		$this->assertCount( 1, $this->fulfillments->get_order_item_fulfillment_data( $items[1]->get_id() ), 'The existing entry is updated.' );
		$this->assertSame( 2, $this->fulfillments->get_order_item_fulfillment_data( $items[1]->get_id() )[0]->quantity );
	}

	public function test_nothing_is_saved_without_posted_quantities(): void {
		$this->screen->save_posted_fulfillment_quantities( 1, array( 'order_item_id' => array( 1 ) ) );

		$this->assertSame( 0, did_action( 'wpo_otd_fulfillment_quantity_saved' ) );
	}

	public function test_the_order_status_is_refreshed_after_saving_items(): void {
		$order = $this->create_order( array( 1 ) );
		$this->fulfillments->save_order_item_fulfillment_quantity( $this->first_item( $order )->get_id(), 1, null );

		$this->screen->refresh_order_fulfillment_status( $order->get_id(), array() );

		$this->assertSame( FulfillmentStatuses::FULFILLED, wc_get_order( $order->get_id() )->get_meta( FulfillmentService::ORDER_FULFILLMENT_STATUS_META_KEY ) );
	}

	public function test_deleting_a_line_drops_its_data_and_refreshes_the_order(): void {
		$order = $this->create_order( array( 1, 1 ) );
		$items = array_values( $order->get_items() );
		$this->fulfillments->save_order_item_fulfillment_quantity( $items[0]->get_id(), 1, null );
		$this->fulfillments->update_order_fulfillment_status_meta( $order->get_id() );

		$this->screen->on_delete_order_item( $items[0]->get_id() );

		$this->assertNull( $this->fulfillments->get_order_item_fulfillment_data( $items[0]->get_id() ) );
		$this->assertSame( FulfillmentStatuses::NOT_FULFILLED, wc_get_order( $order->get_id() )->get_meta( FulfillmentService::ORDER_FULFILLMENT_STATUS_META_KEY ) );
	}

	public function test_deleting_an_unknown_line_does_nothing(): void {
		$this->screen->on_delete_order_item( 999999 );

		$this->assertSame( 0, did_action( 'wpo_otd_fulfillment_data_deleted' ) );
	}

	/** ================================
	 *   Scripts
	 *  ================================ */

	public function test_scripts_load_on_the_edit_screen_with_the_order_id(): void {
		$order = $this->create_order();
		$this->on_order_screen( array( 'action' => 'edit', 'post' => (string) $order->get_id() ) );
		add_filter( 'wpo_otd_order_edit_metabox_script_deps', static fn( $deps ) => array_merge( $deps, array( 'extra-dep' ) ) );

		$this->screen->enqueue_scripts( 'post.php' );

		$this->assertTrue( wp_script_is( 'wpo-otd-order-edit-metabox', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'wpo-otd-order-edit', 'enqueued' ) );
		$this->assertContains( 'extra-dep', wp_scripts()->registered['wpo-otd-order-edit-metabox']->deps );

		// wp_localize_script() turns top-level scalars into strings.
		$settings = $this->localized( 'wpo-otd-order-edit-metabox', 'WPO_OTD_OrderEdit_MetaBox' );
		$this->assertSame( (string) $order->get_id(), $settings['orderId'] );
		$this->assertSame( '', $settings['isWooFulfillmentsEnabled'] );
		$this->assertStringContainsString( 'page=wpo_otd_order_manager#/task-manager/archive', $settings['archivePageUrl'] );

		foreach ( array( 'common', 'skeleton', 'task-card', 'sidebar-modal', 'order-edit' ) as $style ) {
			$this->assertTrue( wp_style_is( "wpo-otd-admin-{$style}", 'enqueued' ), "Style {$style} is not enqueued." );
		}
	}

	public function test_the_order_is_taken_from_the_global_order_first(): void {
		$order               = $this->create_order();
		$GLOBALS['theorder'] = $order;
		$this->on_order_screen( array( 'action' => 'edit', 'post' => '999999' ) );

		$this->screen->enqueue_scripts( 'post.php' );

		$this->assertSame( (string) $order->get_id(), $this->localized( 'wpo-otd-order-edit-metabox', 'WPO_OTD_OrderEdit_MetaBox' )['orderId'] );
	}

	public function test_the_global_post_is_the_last_fallback_on_the_legacy_screen(): void {
		$order = $this->create_order();
		$this->on_order_screen( array( 'action' => 'edit' ) );
		$GLOBALS['post'] = get_post( $order->get_id() );

		$this->screen->enqueue_scripts( 'post.php' );

		$this->assertSame( (string) $order->get_id(), $this->localized( 'wpo-otd-order-edit-metabox', 'WPO_OTD_OrderEdit_MetaBox' )['orderId'] );
	}

	public function test_an_unknown_order_is_passed_as_zero(): void {
		$this->on_order_screen( array( 'action' => 'new', 'id' => '999999' ) );

		$this->screen->enqueue_scripts( 'post-new.php' );

		$this->assertSame( '0', $this->localized( 'wpo-otd-order-edit-metabox', 'WPO_OTD_OrderEdit_MetaBox' )['orderId'] );
	}

	public function test_scripts_stay_off_the_order_list_and_other_screens(): void {
		$this->on_order_screen( array() );
		$this->screen->enqueue_scripts( 'edit.php' );
		$this->assertFalse( wp_script_is( 'wpo-otd-order-edit-metabox', 'enqueued' ), 'The order list shares the screen ID.' );

		set_current_screen( 'dashboard' );
		$this->screen->enqueue_scripts( 'index.php' );
		$this->assertFalse( wp_script_is( 'wpo-otd-order-edit-metabox', 'enqueued' ) );
	}

	/**
	 * Switch to the order screen with the given query args.
	 *
	 * @param array $query
	 *
	 * @return void
	 */
	private function on_order_screen( array $query ): void {
		$_GET = $query;

		set_current_screen( wc_get_page_screen_id( 'shop-order' ) );
	}

	/**
	 * Decode the object a script was localized with.
	 *
	 * @param string $handle
	 * @param string $object_name
	 *
	 * @return array
	 */
	private function localized( string $handle, string $object_name ): array {
		preg_match( "/var {$object_name} = (.+);/", (string) wp_scripts()->get_data( $handle, 'data' ), $matches );

		$this->assertNotEmpty( $matches, "{$handle} was not localized." );

		return json_decode( $matches[1], true );
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
}
