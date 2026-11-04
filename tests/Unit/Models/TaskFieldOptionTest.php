<?php

namespace WPO\OTD\Tests\Unit\Models;

use WPO\OTD\Models\TaskFieldOption;
use WPO\OTD\Tests\TestCase;

class TaskFieldOptionTest extends TestCase {
	public function test_hydrates_from_a_database_row(): void {
		$option = new TaskFieldOption(
			array(
				'id'       => '3',
				'field_id' => '1',
				'slug'     => 'done',
				'label'    => 'Done',
				'color'    => '#28a745',
				'position' => '3',
			)
		);

		$this->assertSame( 3, $option->id );
		$this->assertSame( 1, $option->field_id );
		$this->assertSame( 'done', $option->slug );
		$this->assertSame( 'Done', $option->label );
		$this->assertSame( '#28a745', $option->color );
		$this->assertSame( 3, $option->position );
	}

	public function test_defaults_for_a_new_option(): void {
		$option = new TaskFieldOption( array( 'field_id' => 1, 'slug' => 'new', 'label' => 'New' ) );

		$this->assertSame( 0, $option->id );
		$this->assertSame( '#000000', $option->color );
		$this->assertSame( 0, $option->position );
	}

	public function test_a_null_color_from_the_database_falls_back_to_black(): void {
		// The color column is nullable.
		$option = new TaskFieldOption( array( 'field_id' => 1, 'slug' => 's', 'label' => 'S', 'color' => null ) );

		$this->assertSame( '#000000', $option->color );
	}

	public function test_numbers_are_made_absolute(): void {
		$option = new TaskFieldOption( array( 'id' => -2, 'field_id' => -1, 'slug' => 's', 'label' => 'S', 'position' => -5 ) );

		$this->assertSame( 0, $option->id );
		$this->assertSame( 1, $option->field_id );
		$this->assertSame( 5, $option->position );
	}

	public function test_to_db_array_holds_the_writable_columns(): void {
		$option = new TaskFieldOption( array( 'id' => 3, 'field_id' => 1, 'slug' => 'done', 'label' => 'Done', 'color' => '#fff', 'position' => 2 ) );

		$this->assertSame(
			array(
				'field_id' => 1,
				'slug'     => 'done',
				'label'    => 'Done',
				'color'    => '#fff',
				'position' => 2,
			),
			$option->to_db_array()
		);
	}

	public function test_fill_cannot_move_an_option_to_another_field(): void {
		$option = new TaskFieldOption( array( 'id' => 3, 'field_id' => 1, 'slug' => 'done', 'label' => 'Done' ) );

		$option->fill( array( 'id' => 9, 'field_id' => 8, 'label' => 'Finished', 'position' => 4 ) );

		$this->assertSame( 3, $option->id );
		$this->assertSame( 1, $option->field_id );
		$this->assertSame( 'Finished', $option->label );
		$this->assertSame( 4, $option->position );
	}
}
