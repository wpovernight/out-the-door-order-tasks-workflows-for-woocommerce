<?php

namespace WPO\OTD\Tests\Unit\Enums;

use WPO\OTD\Enums\TaskFieldTypes;
use WPO\OTD\Tests\TestCase;

class TaskFieldTypesTest extends TestCase {
	public function test_values_are_stable(): void {
		// Stored in the task_fields.type column.
		$this->assertSame( 'text', TaskFieldTypes::TEXT );
		$this->assertSame( 'number', TaskFieldTypes::NUMBER );
		$this->assertSame( 'select', TaskFieldTypes::SELECT );
		$this->assertSame( 'date', TaskFieldTypes::DATE );
	}

	public function test_all_lists_every_type(): void {
		$this->assertSame( array( 'text', 'number', 'select', 'date' ), TaskFieldTypes::all() );
	}

	/**
	 * @dataProvider validity_provider
	 */
	public function test_is_valid( string $type, bool $expected ): void {
		$this->assertSame( $expected, TaskFieldTypes::is_valid( $type ) );
	}

	public function validity_provider(): array {
		return array(
			'text'       => array( 'text', true ),
			'number'     => array( 'number', true ),
			'select'     => array( 'select', true ),
			'date'       => array( 'date', true ),
			'empty'      => array( '', false ),
			'wrong case' => array( 'Text', false ),
			'user'       => array( 'user', false ),
			'checkbox'   => array( 'checkbox', false ),
		);
	}
}
