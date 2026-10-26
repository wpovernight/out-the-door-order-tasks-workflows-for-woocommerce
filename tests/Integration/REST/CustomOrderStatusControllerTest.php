<?php

namespace WPO\OTD\Tests\Integration\REST;

use WPO\OTD\Services\CustomOrderStatusService;

class CustomOrderStatusControllerTest extends RestTestCase {
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

	public function test_reading_a_missing_status(): void {
		$this->assertResponse( 404, $this->request( 'GET', '/custom-order-statuses/999999' ), 'not_found' );
	}

	public function test_deleting_a_status(): void {
		$id = $this->create_status( 'Packed' );

		$response = $this->request( 'DELETE', "/custom-order-statuses/{$id}" );

		$this->assertResponse( 200, $response );
		$this->assertTrue( $this->json( $response )['data']['success'] );
		$this->assertNull( $this->make( CustomOrderStatusService::class )->find( $id ) );
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
