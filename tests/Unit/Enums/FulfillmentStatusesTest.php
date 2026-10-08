<?php

namespace WPO\OTD\Tests\Unit\Enums;

use WPO\OTD\Enums\FulfillmentStatuses;
use WPO\OTD\Tests\TestCase;

class FulfillmentStatusesTest extends TestCase {
	public function test_values_are_stable(): void {
		// Stored in order meta and exposed through hooks and the REST API.
		$this->assertSame( 'not-fulfilled', FulfillmentStatuses::NOT_FULFILLED );
		$this->assertSame( 'fulfilled', FulfillmentStatuses::FULFILLED );
		$this->assertSame( 'partially-fulfilled', FulfillmentStatuses::PARTIALLY_FULFILLED );
	}

	public function test_all_lists_every_status(): void {
		$this->assertSame( array( 'not-fulfilled', 'fulfilled', 'partially-fulfilled' ), FulfillmentStatuses::all() );
	}

	/**
	 * @dataProvider validity_provider
	 */
	public function test_is_valid( string $status, bool $expected ): void {
		$this->assertSame( $expected, FulfillmentStatuses::is_valid( $status ) );
	}

	public function validity_provider(): array {
		return array(
			'not fulfilled'       => array( 'not-fulfilled', true ),
			'fulfilled'           => array( 'fulfilled', true ),
			'partially fulfilled' => array( 'partially-fulfilled', true ),
			'empty'               => array( '', false ),
			'wrong case'          => array( 'Fulfilled', false ),
			'underscored'         => array( 'not_fulfilled', false ),
			'padded'              => array( ' fulfilled', false ),
		);
	}
}
