<?php

namespace WPO\OTD\Tests\Unit\Enums;

use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Tests\TestCase;

/**
 * The seeded field IDs are covered against the real installer in Integration\Core\InstallerTest.
 */
class DefaultTaskFieldsTest extends TestCase {
	public function test_ids_are_stable(): void {
		$this->assertSame( 1, DefaultTaskFields::STATUS );
		$this->assertSame( 2, DefaultTaskFields::POSITION );
		$this->assertSame( 3, DefaultTaskFields::CREATOR );
		$this->assertSame( 4, DefaultTaskFields::ORDER );
		$this->assertSame( 5, DefaultTaskFields::DUE_DATE );
		$this->assertSame( 6, DefaultTaskFields::DONE_DATE );
		$this->assertSame( 7, DefaultTaskFields::ARCHIVED_DATE );
		$this->assertSame( 8, DefaultTaskFields::PRIORITY );
	}

	public function test_the_done_date_format_is_stable(): void {
		$this->assertSame( 'Y-m-d H:i:s', DefaultTaskFields::DONE_DATE_FORMAT );
	}

	public function test_all_lists_every_id_once(): void {
		$this->assertSame( array( 1, 2, 3, 4, 5, 6, 7, 8 ), DefaultTaskFields::all() );
	}

	public function test_every_field_but_priority_is_protected(): void {
		$this->assertSame(
			array_values( array_diff( DefaultTaskFields::all(), array( DefaultTaskFields::PRIORITY ) ) ),
			DefaultTaskFields::protected()
		);
	}

	public function test_editable_and_required_subsets(): void {
		$this->assertSame( array( DefaultTaskFields::STATUS, DefaultTaskFields::PRIORITY ), DefaultTaskFields::editable() );
		$this->assertSame( array( DefaultTaskFields::STATUS, DefaultTaskFields::POSITION ), DefaultTaskFields::required() );
	}

	/**
	 * @dataProvider membership_provider
	 */
	public function test_membership_checks( int $field_id, bool $default, bool $protected, bool $editable, bool $required ): void {
		$this->assertSame( $default, DefaultTaskFields::is_default( $field_id ) );
		$this->assertSame( $protected, DefaultTaskFields::is_protected( $field_id ) );
		$this->assertSame( $editable, DefaultTaskFields::is_editable( $field_id ) );
		$this->assertSame( $required, DefaultTaskFields::is_required( $field_id ) );
	}

	public function membership_provider(): array {
		return array(
			'status'        => array( DefaultTaskFields::STATUS, true, true, true, true ),
			'position'      => array( DefaultTaskFields::POSITION, true, true, false, true ),
			'creator'       => array( DefaultTaskFields::CREATOR, true, true, false, false ),
			'archived date' => array( DefaultTaskFields::ARCHIVED_DATE, true, true, false, false ),
			'priority'      => array( DefaultTaskFields::PRIORITY, true, false, true, false ),
			'custom field'  => array( 9, false, false, false, false ),
			'zero'          => array( 0, false, false, false, false ),
		);
	}
}
