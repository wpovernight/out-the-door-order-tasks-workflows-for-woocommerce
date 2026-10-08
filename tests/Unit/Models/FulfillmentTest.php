<?php

namespace WPO\OTD\Tests\Unit\Models;

use WPO\OTD\Models\Fulfillment;
use WPO\OTD\Tests\TestCase;

class FulfillmentTest extends TestCase {
	public function test_hydrates_from_stored_meta(): void {
		$fulfillment = new Fulfillment( array( 'id' => '2', 'quantity' => '3' ) );

		$this->assertSame( 2, $fulfillment->id );
		$this->assertSame( 3, $fulfillment->quantity );
	}

	public function test_defaults_to_an_unsaved_empty_fulfillment(): void {
		$fulfillment = new Fulfillment();

		$this->assertSame( 0, $fulfillment->id );
		$this->assertSame( 0, $fulfillment->quantity );
	}

	public function test_negative_numbers_are_sanitized(): void {
		$fulfillment = new Fulfillment( array( 'id' => -2, 'quantity' => -3 ) );

		$this->assertSame( 0, $fulfillment->id );
		$this->assertSame( 3, $fulfillment->quantity );
	}

	public function test_to_array_is_the_stored_meta_shape(): void {
		$this->assertSame( array( 'id' => 2, 'quantity' => 3 ), ( new Fulfillment( array( 'id' => 2, 'quantity' => 3 ) ) )->to_array() );
	}
}
