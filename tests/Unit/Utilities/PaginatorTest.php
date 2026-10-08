<?php

namespace WPO\OTD\Tests\Unit\Utilities;

use WPO\OTD\Tests\TestCase;
use WPO\OTD\Utilities\Paginator;

class PaginatorTest extends TestCase {
	public function test_exposes_the_page(): void {
		$paginator = new Paginator( array( 'k', 'l' ), 12, 5, 3 );

		$this->assertSame( array( 'k', 'l' ), $paginator->items() );
		$this->assertSame( 12, $paginator->total() );
		$this->assertSame( 5, $paginator->per_page() );
		$this->assertSame( 3, $paginator->current_page() );
		$this->assertSame( 3, $paginator->last_page() );
		$this->assertSame( 11, $paginator->from() );
		$this->assertSame( 12, $paginator->to() );
	}

	public function test_a_full_middle_page(): void {
		$paginator = new Paginator( array( 'f', 'g', 'h', 'i', 'j' ), 12, 5, 2 );

		$this->assertSame( 6, $paginator->from() );
		$this->assertSame( 10, $paginator->to() );
	}

	public function test_last_page_rounds_up(): void {
		$this->assertSame( 4, ( new Paginator( array(), 31, 10, 1 ) )->last_page() );
		$this->assertSame( 3, ( new Paginator( array(), 30, 10, 1 ) )->last_page() );
	}

	public function test_an_empty_result_has_one_page_and_no_range(): void {
		$paginator = new Paginator( array(), 0, 10, 1 );

		$this->assertSame( 1, $paginator->last_page() );
		$this->assertNull( $paginator->from() );
		$this->assertNull( $paginator->to() );
	}

	public function test_a_page_past_the_end_has_no_range(): void {
		$paginator = new Paginator( array(), 12, 5, 9 );

		$this->assertSame( 9, $paginator->current_page() );
		$this->assertNull( $paginator->from() );
		$this->assertNull( $paginator->to() );
	}

	public function test_out_of_range_arguments_are_clamped(): void {
		$paginator = new Paginator( array( 'a' ), -5, 0, -2 );

		$this->assertSame( 0, $paginator->total() );
		$this->assertSame( 1, $paginator->per_page() );
		$this->assertSame( 1, $paginator->current_page() );
		$this->assertSame( 1, $paginator->last_page() );
		$this->assertNull( $paginator->from(), 'Nothing is in range when the total is zero.' );
	}

	public function test_full_wraps_a_complete_list_as_one_page(): void {
		$paginator = Paginator::full( array( 'a', 'b', 'c' ) );

		$this->assertSame( array( 'a', 'b', 'c' ), $paginator->items() );
		$this->assertSame( 3, $paginator->total() );
		$this->assertSame( 3, $paginator->per_page() );
		$this->assertSame( 1, $paginator->current_page() );
		$this->assertSame( 1, $paginator->last_page() );
		$this->assertSame( 1, $paginator->from() );
		$this->assertSame( 3, $paginator->to() );
	}

	public function test_full_with_an_empty_list(): void {
		$paginator = Paginator::full( array() );

		$this->assertSame( 0, $paginator->total() );
		$this->assertSame( 1, $paginator->per_page() );
		$this->assertNull( $paginator->from() );
	}
}
