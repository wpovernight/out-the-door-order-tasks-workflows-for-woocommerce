<?php

namespace WPO\OTD\Tests\Unit\Models;

use WPO\OTD\Contracts\ArraySerializableModel;
use WPO\OTD\Models\CustomOrderStatus;
use WPO\OTD\Tests\TestCase;

class CustomOrderStatusTest extends TestCase {
	public function test_hydrates_from_a_database_row(): void {
		$status = new CustomOrderStatus(
			array(
				'id'          => '4',
				'status_key'  => 'awaiting-pickup',
				'label'       => 'Awaiting pickup',
				'background'  => '#ffffff',
				'is_deleting' => '1',
			)
		);

		$this->assertInstanceOf( ArraySerializableModel::class, $status );
		$this->assertSame( 4, $status->id );
		$this->assertSame( 'awaiting-pickup', $status->status_key );
		$this->assertSame( 'Awaiting pickup', $status->label );
		$this->assertSame( '#ffffff', $status->background );
		$this->assertTrue( $status->is_deleting );
	}

	public function test_defaults_for_a_new_status(): void {
		$status = new CustomOrderStatus( array( 'label' => 'Packed' ) );

		$this->assertSame( 0, $status->id );
		$this->assertSame( 'packed', $status->status_key, 'The key falls back to the sanitized label.' );
		$this->assertSame( '#ccc', $status->background );
		$this->assertFalse( $status->is_deleting );
	}

	public function test_a_given_key_is_sanitized(): void {
		$this->assertSame( 'ready-to-ship', ( new CustomOrderStatus( array( 'label' => 'X', 'status_key' => 'Ready To Ship' ) ) )->status_key );
	}

	public function test_the_woocommerce_key_is_prefixed(): void {
		$this->assertSame( 'wc-packed', ( new CustomOrderStatus( array( 'label' => 'Packed' ) ) )->get_prefixed_status_key() );
	}

	public function test_to_array_and_to_db_array(): void {
		$status = new CustomOrderStatus(
			array(
				'id'         => 4,
				'label'      => 'Packed',
				'background' => '#000000',
				'foreground' => '#ffffff',
			)
		);

		$this->assertSame(
			array(
				'id'          => 4,
				'status_key'  => 'packed',
				'label'       => 'Packed',
				'background'  => '#000000',
				'foreground'  => '#ffffff',
				'is_deleting' => false,
			),
			$status->to_array()
		);

		$this->assertSame(
			array(
				'status_key'  => 'packed',
				'label'       => 'Packed',
				'background'  => '#000000',
				'is_deleting' => 0,
			),
			$status->to_db_array(),
			'The foreground is derived, never stored.'
		);
	}

	/**
	 * @dataProvider foreground_provider
	 */
	public function test_the_foreground_is_derived_from_the_background( string $background, string $foreground ): void {
		$this->assertSame( $foreground, ( new CustomOrderStatus( array( 'label' => 'X', 'background' => $background ) ) )->foreground );
	}

	public function foreground_provider(): array {
		return array(
			// Light backgrounds are darkened by (175, 175, 150), clamped at 0.
			'white'                  => array( '#ffffff', '#505069' ),
			'shorthand white'        => array( '#fff', '#505069' ),
			'without hash'           => array( 'ffffff', '#505069' ),
			'the default light grey' => array( '#ccc', '#1d1d36' ),
			'light mid grey'         => array( '#909090', '#000000' ),
			// Dark backgrounds are lightened by the same amounts, clamped at 255.
			'black'                  => array( '#000000', '#afaf96' ),
			'navy'                   => array( '#1a237e', '#c9d2ff' ),
			'dark mid grey'          => array( '#707070', '#ffffff' ),
			// Lightened blue (175, 175, 255) stays under the 4.5:1 WCAG ratio, so it falls back to white.
			'blue'                   => array( '#0000ff', '#ffffff' ),
		);
	}

	public function test_fill_cannot_start_a_deletion(): void {
		$status = new CustomOrderStatus( array( 'id' => 4, 'label' => 'Packed' ) );

		$status->fill( array( 'id' => 9, 'is_deleting' => true, 'label' => 'Boxed' ) );

		$this->assertSame( 4, $status->id );
		$this->assertFalse( $status->is_deleting );
		$this->assertSame( 'Boxed', $status->label );
	}

	public function test_a_given_foreground_is_kept(): void {
		$this->assertSame( '#123456', ( new CustomOrderStatus( array( 'label' => 'X', 'background' => '#fff', 'foreground' => '#123456' ) ) )->foreground );
	}
}
