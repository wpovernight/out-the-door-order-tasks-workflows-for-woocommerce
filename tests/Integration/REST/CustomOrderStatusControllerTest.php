<?php

namespace WPO\OTD\Tests\Integration\REST;

use WPO\OTD\Repositories\CustomOrderStatusRepository;
use WPO\OTD\Services\CustomOrderStatusService;

class CustomOrderStatusControllerTest extends RestTestCase {
	public function test_the_routes_live_under_the_woocommerce_namespace(): void {
		$routes = rest_get_server()->get_routes( 'wc/v3/wpo/otd' );

		$this->assertSame(
			array( 'GET', 'POST' ),
			$this->methods( $routes['/wc/v3/wpo/otd/custom-order-statuses'] )
		);
		$this->assertSame(
			array( 'DELETE', 'GET', 'PATCH', 'POST', 'PUT' ),
			$this->methods( $routes['/wc/v3/wpo/otd/custom-order-statuses/(?P<id>\d+)'] )
		);
	}

	public function test_guests_are_turned_away(): void {
		wp_set_current_user( 0 );

		$this->assertResponse( 401, $this->request( 'GET', '/custom-order-statuses' ), 'rest_forbidden' );
	}

	public function test_creating_and_reading_a_status(): void {
		$created = $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Awaiting Pickup', 'background' => '#000' ) );

		$this->assertResponse( 200, $created );
		$data = $this->json( $created )['data'];
		$this->assertSame(
			array(
				'id'          => $data['id'],
				'status_key'  => 'awaiting-pickup',
				'label'       => 'Awaiting Pickup',
				'background'  => '#000',
				'foreground'  => '#afaf96',
				'is_deleting' => false,
			),
			$data
		);

		$this->assertSame( $data, $this->json( $this->request( 'GET', "/custom-order-statuses/{$data['id']}" ) )['data'] );
	}

	/**
	 * @dataProvider invalid_status_provider
	 */
	public function test_creating_an_invalid_status( array $body, string $field ): void {
		$response = $this->request( 'POST', '/custom-order-statuses', $body );

		$this->assertResponse( 400, $response, 'invalid_data' );
		$this->assertArrayHasKey( $field, $response->get_data()['data']['errors'] );
	}

	public function invalid_status_provider(): array {
		return array(
			'missing label'     => array( array( 'background' => '#fff' ), 'label' ),
			'label too long'    => array( array( 'label' => str_repeat( 'a', 256 ) ), 'label' ),
			'key with a space'  => array( array( 'label' => 'X', 'status_key' => 'has space' ), 'status_key' ),
			'key too long'      => array( array( 'label' => 'X', 'status_key' => str_repeat( 'a', 65 ) ), 'status_key' ),
			'named background'  => array( array( 'label' => 'X', 'background' => 'red' ), 'background' ),
			'bad foreground'    => array( array( 'label' => 'X', 'foreground' => '#ff' ), 'foreground' ),
		);
	}

	/**
	 * @dataProvider keyless_key_provider
	 */
	public function test_a_key_without_a_letter_or_digit_is_refused( string $key ): void {
		$created = $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Packed', 'status_key' => $key ) );

		$this->assertResponse( 400, $created, 'invalid_data' );
		$this->assertArrayHasKey( 'status_key', $created->get_data()['data']['errors'] );

		$id      = $this->create_status( 'Boxed' );
		$updated = $this->request( 'PUT', "/custom-order-statuses/{$id}", array( 'status_key' => $key ) );

		$this->assertResponse( 400, $updated, 'invalid_data' );
		$this->assertArrayHasKey( 'status_key', $updated->get_data()['data']['errors'] );
		$this->assertSame( 'boxed', $this->make( CustomOrderStatusService::class )->find( $id )->status_key );
	}

	public function keyless_key_provider(): array {
		return array(
			'a dash'      => array( '-' ),
			'two dashes'  => array( '--' ),
			'underscores' => array( '__' ),
			'mixed'       => array( '-_-' ),
		);
	}

	public function test_a_key_with_a_letter_or_digit_among_dashes_is_accepted(): void {
		$response = $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Packed', 'status_key' => '-7-' ) );

		$this->assertResponse( 200, $response );
		$this->assertSame( '7', $this->json( $response )['data']['status_key'] );
	}

	/**
	 * @dataProvider keyless_label_provider
	 */
	public function test_a_label_without_latin_letters_or_digits_needs_a_key( string $label ): void {
		$response = $this->request( 'POST', '/custom-order-statuses', array( 'label' => $label ) );

		$this->assertResponse( 400, $response, 'invalid_data' );
		$this->assertSame(
			array( 'The label has no Latin letter or digit to make a status key from: send a status_key of up to 17 letters, digits, - and _.' ),
			$response->get_data()['data']['errors']['status_key']
		);

		$created = $this->request( 'POST', '/custom-order-statuses', array( 'label' => $label, 'status_key' => 'shipped' ) );
		$this->assertResponse( 200, $created );
		$this->assertSame( $label, $this->json( $created )['data']['label'] );
	}

	public function keyless_label_provider(): array {
		return array(
			'Persian'       => array( 'ارسال شده' ),
			'only an emoji' => array( '📦' ),
			'only signs'    => array( '***' ),
		);
	}

	/**
	 * @dataProvider symbol_label_provider
	 */
	public function test_a_label_with_symbols_makes_a_key_of_its_latin_letters_and_digits( string $label, string $key ): void {
		$response = $this->request( 'POST', '/custom-order-statuses', array( 'label' => $label ) );

		$this->assertResponse( 200, $response );
		$this->assertSame( $key, $this->json( $response )['data']['status_key'] );
	}

	public function symbol_label_provider(): array {
		return array(
			'a trailing check mark' => array( 'Done ✓', 'done' ),
			'a leading check mark'  => array( '✓ Done', 'done' ),
			'an emoji in between'   => array( 'Awaiting 📦 shipment', 'awaiting-shipment' ),
			'mixed with Persian'    => array( 'Sent ارسال 2', 'sent-2' ),
		);
	}

	/**
	 * @dataProvider reserved_key_provider
	 */
	public function test_the_key_of_another_order_status_is_refused( string $key ): void {
		$created = $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Mine', 'status_key' => $key ) );

		$this->assertResponse( 400, $created, 'invalid_data' );
		$this->assertSame(
			array( 'This status key belongs to an order status of WooCommerce or of another plugin. Choose another one.' ),
			$created->get_data()['data']['errors']['status_key']
		);

		$id      = $this->create_status( 'Mine' );
		$updated = $this->request( 'PUT', "/custom-order-statuses/{$id}", array( 'status_key' => $key ) );

		$this->assertResponse( 400, $updated, 'invalid_data' );
		$this->assertSame( 'mine', $this->make( CustomOrderStatusService::class )->find( $id )->status_key );
	}

	public function reserved_key_provider(): array {
		return array(
			'processing'     => array( 'processing' ),
			'on-hold'        => array( 'on-hold' ),
			'another case'   => array( 'Completed' ),
			'checkout-draft' => array( 'checkout-draft' ),
			'refunded'       => array( 'refunded' ),
		);
	}

	public function test_a_label_that_makes_the_key_of_another_order_status_is_refused(): void {
		$response = $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Processing' ) );

		$this->assertResponse( 400, $response, 'invalid_data' );
		$this->assertSame(
			array( 'The label makes the status key processing, which belongs to an order status of WooCommerce or of another plugin. Send a status_key.' ),
			$response->get_data()['data']['errors']['status_key']
		);
		$this->assertResponse( 200, $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Processing', 'status_key' => 'in-the-works' ) ) );
	}

	public function test_the_key_of_another_plugins_order_status_is_refused(): void {
		add_filter( 'wc_order_statuses', static fn( $statuses ) => $statuses + array( 'wc-awaiting-pickup' => 'Awaiting pickup' ) );

		$this->assertResponse( 400, $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Awaiting pickup' ) ), 'invalid_data' );
		$this->assertResponse( 400, $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Mine', 'status_key' => 'awaiting-pickup' ) ), 'invalid_data' );
	}

	public function test_a_long_label_makes_a_key_cut_to_17_characters(): void {
		$response = $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Awaiting payment ok' ) );

		$this->assertResponse( 200, $response );
		$this->assertSame( 'awaiting-payment', $this->json( $response )['data']['status_key'], 'Cut to 17, without the trailing dash.' );

		$response = $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Awaiting supplier stock' ) );
		$this->assertSame( 'awaiting-supplier', $this->json( $response )['data']['status_key'] );
	}

	public function test_a_sent_key_may_hold_17_characters_and_no_more(): void {
		$too_long = $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Long', 'status_key' => str_repeat( 'a', 18 ) ) );

		$this->assertResponse( 400, $too_long, 'invalid_data' );
		$this->assertSame( array( 'The status_key field may not be longer than 17 characters.' ), $too_long->get_data()['data']['errors']['status_key'] );

		$this->assertResponse( 200, $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Long', 'status_key' => str_repeat( 'a', 17 ) ) ) );

		$id = $this->create_status( 'Short' );
		$this->assertResponse( 400, $this->request( 'PUT', "/custom-order-statuses/{$id}", array( 'status_key' => str_repeat( 'b', 18 ) ) ), 'invalid_data' );
		$this->assertResponse( 200, $this->request( 'PUT', "/custom-order-statuses/{$id}", array( 'status_key' => str_repeat( 'b', 17 ) ) ) );
	}

	public function test_a_taken_key_at_the_limit_is_cut_to_fit_its_suffix(): void {
		foreach ( array( 'awaiting-supplier', 'abcdefghijklmn-pq' ) as $key ) {
			$this->request( 'POST', '/custom-order-statuses', array( 'label' => 'First', 'status_key' => $key ) );
		}

		$second = $this->json( $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Second', 'status_key' => 'awaiting-supplier' ) ) )['data'];
		$third  = $this->json( $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Third', 'status_key' => 'awaiting-supplier' ) ) )['data'];
		$cut    = $this->json( $this->request( 'POST', '/custom-order-statuses', array( 'label' => 'Cut', 'status_key' => 'abcdefghijklmn-pq' ) ) )['data'];

		$this->assertSame( 'awaiting-suppli-2', $second['status_key'] );
		$this->assertSame( 'awaiting-suppli-3', $third['status_key'] );
		$this->assertSame( 'abcdefghijklmn-2', $cut['status_key'], 'A cut that ends on a dash drops it.' );
	}

	public function test_listing_statuses_in_full_or_by_page(): void {
		foreach ( array( 'A', 'B', 'C' ) as $label ) {
			$this->request( 'POST', '/custom-order-statuses', array( 'label' => $label ) );
		}

		$full = $this->json( $this->request( 'GET', '/custom-order-statuses' ) );
		$this->assertSame( array( 'A', 'B', 'C' ), array_column( $full['data'], 'label' ) );
		$this->assertArrayNotHasKey( 'meta', $full );

		$page = $this->json( $this->request( 'GET', '/custom-order-statuses', null, array( 'page' => 2, 'per_page' => 2 ) ) );
		$this->assertSame( array( 'C' ), array_column( $page['data'], 'label' ) );
		$this->assertSame( 3, $page['meta']['total'] );
		$this->assertSame( 2, $page['meta']['last_page'] );
	}

	public function test_a_page_too_far_out_for_its_offset_to_fit_is_empty_with_the_real_total(): void {
		$this->request( 'POST', '/custom-order-statuses', array( 'label' => 'A' ) );

		$page = $this->json( $this->request( 'GET', '/custom-order-statuses', null, array( 'page' => (string) PHP_INT_MAX, 'per_page' => 2 ) ) );

		$this->assertSame( array(), $page['data'] );
		$this->assertSame( 1, $page['meta']['total'] );
	}

	public function test_updating_a_status(): void {
		$id = $this->create_status( 'Packed' );

		$response = $this->request( 'PUT', "/custom-order-statuses/{$id}", array( 'label' => 'Boxed', 'background' => '#123456' ) );

		$this->assertResponse( 200, $response );
		$this->assertSame( 'Boxed', $this->json( $response )['data']['label'] );
		$this->assertResponse( 400, $this->request( 'PUT', "/custom-order-statuses/{$id}", array( 'background' => 'blue' ) ), 'invalid_data' );
		$this->assertResponse( 404, $this->request( 'PUT', '/custom-order-statuses/999999', array( 'label' => 'Ghost' ) ), 'not_found' );
	}

	/**
	 * @dataProvider null_update_provider
	 */
	public function test_updating_a_status_to_null_is_refused( string $field ): void {
		$id = $this->create_status( 'Packed' );

		$response = $this->request( 'PUT', "/custom-order-statuses/{$id}", array( $field => null ) );

		$this->assertResponse( 400, $response, 'invalid_data' );
		$this->assertSame( array( "The {$field} field may not be null." ), $response->get_data()['data']['errors'][ $field ] );
		$this->assertSame( 'Packed', $this->make( CustomOrderStatusService::class )->find( $id )->label );
	}

	public function null_update_provider(): array {
		return array(
			'label'      => array( 'label' ),
			'status_key' => array( 'status_key' ),
			'background' => array( 'background' ),
			'foreground' => array( 'foreground' ),
		);
	}

	public function test_updating_a_status_to_a_key_another_status_uses_is_a_conflict(): void {
		$this->create_status( 'Packed' );
		$id = $this->create_status( 'Boxed' );

		foreach ( array( 'packed', 'PACKED' ) as $key ) {
			$response = $this->request( 'PUT', "/custom-order-statuses/{$id}", array( 'status_key' => $key ) );

			$this->assertResponse( 409, $response, 'status_key_taken' );
			$this->assertSame( 'Another custom order status already uses this status key.', $response->get_data()['message'] );
		}

		$this->assertSame( 'boxed', $this->make( CustomOrderStatusService::class )->find( $id )->status_key );
	}

	public function test_the_key_of_a_status_being_deleted_cannot_change(): void {
		$id = $this->create_status( 'Packed' );
		( new CustomOrderStatusRepository() )->where( 'id', $id )->update( array( 'is_deleting' => 1 ) );

		$response = $this->request( 'PUT', "/custom-order-statuses/{$id}", array( 'status_key' => 'boxed' ) );

		$this->assertResponse( 409, $response, 'status_being_deleted' );
		$this->assertSame( 'This custom order status is being deleted, so its status key cannot change.', $response->get_data()['message'] );
		$this->assertSame( 'packed', $this->make( CustomOrderStatusService::class )->find( $id )->status_key );

		$this->assertResponse( 200, $this->request( 'PUT', "/custom-order-statuses/{$id}", array( 'status_key' => 'packed', 'label' => 'Packed up' ) ) );
	}

	public function test_a_put_cannot_clear_a_deletion_that_starts_while_it_runs(): void {
		global $wpdb;

		$id      = $this->create_status( 'Packed' );
		$flagged = false;

		// The deletion flags the row between the update's read and its write.
		add_filter(
			'query',
			static function ( $query ) use ( $id, &$flagged, $wpdb ) {
				if ( ! $flagged && preg_match( '/^UPDATE `\w+wpo_otd_custom_statuses`/', (string) $query ) ) {
					$flagged = true;
					$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->prefix}wpo_otd_custom_statuses` SET is_deleting = 1 WHERE id = %d", $id ) );
				}

				return $query;
			}
		);

		$response = $this->request( 'PUT', "/custom-order-statuses/{$id}", array( 'label' => 'Boxed', 'is_deleting' => false ) );

		$this->assertResponse( 200, $response );
		$this->assertTrue( $flagged );
		$this->assertTrue( $this->make( CustomOrderStatusService::class )->find( $id )->is_deleting );
		$this->assertSame( 'Boxed', $this->make( CustomOrderStatusService::class )->find( $id )->label );
	}

	public function test_a_delete_while_another_request_changes_the_status_is_a_conflict(): void {
		$id = $this->create_status( 'Packed' );

		$connection = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$connection->set_prefix( $GLOBALS['wpdb']->prefix );
		$other = new CustomOrderStatusRepository();
		( new \ReflectionProperty( $other, 'wpdb' ) )->setValue( $other, $connection );
		$this->assertTrue( $other->acquire_lock( "custom_status_deletion_{$id}", 0 ) );

		try {
			$busy = $this->request( 'DELETE', "/custom-order-statuses/{$id}" );

			( new CustomOrderStatusRepository() )->where( 'id', $id )->update( array( 'is_deleting' => 1 ) );
			$deleting = $this->request( 'DELETE', "/custom-order-statuses/{$id}" );
		} finally {
			$other->release_lock( "custom_status_deletion_{$id}" );
		}

		$this->assertResponse( 409, $busy, 'status_busy' );
		$this->assertSame( 'Another request is changing this custom order status, so it was not deleted. Try again in a moment.', $busy->get_data()['message'] );
		$this->assertResponse( 202, $deleting );
		$this->assertNotNull( $this->make( CustomOrderStatusService::class )->find( $id ) );
	}

	public function test_a_put_while_another_request_holds_the_status_is_a_conflict(): void {
		$id = $this->create_status( 'Packed' );

		$connection = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$connection->set_prefix( $GLOBALS['wpdb']->prefix );
		$other = new CustomOrderStatusRepository();
		( new \ReflectionProperty( $other, 'wpdb' ) )->setValue( $other, $connection );
		$this->assertTrue( $other->acquire_lock( "custom_status_deletion_{$id}", 0 ) );

		try {
			$response = $this->request( 'PUT', "/custom-order-statuses/{$id}", array( 'label' => 'Boxed' ) );
		} finally {
			$other->release_lock( "custom_status_deletion_{$id}" );
		}

		$this->assertResponse( 409, $response, 'status_being_deleted' );
		$this->assertSame( 'Packed', $this->make( CustomOrderStatusService::class )->find( $id )->label );
	}

	public function test_a_status_may_keep_its_own_key_or_take_a_free_one(): void {
		$id = $this->create_status( 'Boxed' );

		$this->assertResponse( 200, $this->request( 'PUT', "/custom-order-statuses/{$id}", array( 'status_key' => 'boxed', 'label' => 'Boxed up' ) ) );
		$response = $this->request( 'PUT', "/custom-order-statuses/{$id}", array( 'status_key' => 'Shipped_Out' ) );

		$this->assertResponse( 200, $response );
		$this->assertSame( 'shipped_out', $this->json( $response )['data']['status_key'], 'The key is stored as creating it stores it.' );
	}

	public function test_reading_a_missing_status(): void {
		$this->assertResponse( 404, $this->request( 'GET', '/custom-order-statuses/999999' ), 'not_found' );
	}

	public function test_deleting_a_status(): void {
		$id = $this->create_status( 'Packed' );

		$response = $this->request( 'DELETE', "/custom-order-statuses/{$id}" );

		$this->assertResponse( 200, $response );
		$this->assertSame(
			array(
				'success' => true,
				'message' => 'Custom order status deleted successfully',
			),
			$this->json( $response )['data']
		);
		$this->assertNull( $this->make( CustomOrderStatusService::class )->find( $id ) );
	}

	public function test_a_status_the_database_refuses_to_delete_is_a_server_error(): void {
		$id      = $this->create_status( 'Packed' );
		$deleted = array();
		add_action(
			'wpo_otd_custom_order_status_deleted',
			static function ( $status_id ) use ( &$deleted ) {
				$deleted[] = $status_id;
			}
		);
		add_filter( 'wpo_otd_logging_enabled', '__return_false' );
		$this->fail_queries( '/^DELETE FROM `\w+wpo_otd_custom_statuses`/' );

		$response = $this->request( 'DELETE', "/custom-order-statuses/{$id}" );

		$this->assertResponse( 500, $response, 'deletion_failed' );
		$this->assertSame( 'Failed to delete custom order status.', $response->get_data()['message'] );
		$this->assertSame( array(), $deleted );
		$this->assertNotNull( $this->make( CustomOrderStatusService::class )->find( $id ) );
	}

	public function test_deleting_a_missing_status_is_not_found(): void {
		$this->assertResponse( 404, $this->request( 'DELETE', '/custom-order-statuses/999999' ), 'not_found' );

		$id = $this->create_status( 'Packed' );
		$this->request( 'DELETE', "/custom-order-statuses/{$id}" );

		$this->assertResponse( 404, $this->request( 'DELETE', "/custom-order-statuses/{$id}" ), 'not_found' );
	}

	public function test_deleting_a_status_whose_orders_are_still_being_moved_is_only_scheduled(): void {
		$id = $this->create_status( 'Packed' );
		( new CustomOrderStatusRepository() )->where( 'id', $id )->update( array( 'is_deleting' => 1 ) );

		$response = $this->request( 'DELETE', "/custom-order-statuses/{$id}" );

		$this->assertResponse( 202, $response );
		$this->assertSame(
			array(
				'success' => true,
				'message' => 'Custom order status deletion scheduled: it is deleted once its orders are moved to another status',
			),
			$this->json( $response )['data']
		);
		$this->assertNotNull( $this->make( CustomOrderStatusService::class )->find( $id ), 'The status stays until its orders are moved.' );
	}

	/**
	 * The HTTP methods a route's handlers answer, sorted.
	 *
	 * @param array $handlers
	 *
	 * @return string[]
	 */
	private function methods( array $handlers ): array {
		$methods = array();

		foreach ( $handlers as $handler ) {
			$methods = array_merge( $methods, array_keys( array_filter( $handler['methods'] ) ) );
		}

		sort( $methods );

		return array_values( array_unique( $methods ) );
	}

	/**
	 * Create a status through the API and return its ID.
	 *
	 * @param string $label
	 *
	 * @return int
	 */
	private function create_status( string $label ): int {
		return $this->json( $this->request( 'POST', '/custom-order-statuses', array( 'label' => $label ) ) )['data']['id'];
	}
}
