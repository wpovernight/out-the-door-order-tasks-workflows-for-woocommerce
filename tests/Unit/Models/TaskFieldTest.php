<?php

namespace WPO\OTD\Tests\Unit\Models;

use Brain\Monkey\Functions;
use Mockery;
use WPO\OTD\Core\Logger;
use WPO\OTD\Models\TaskField;
use WPO\OTD\Models\TaskFieldOption;
use WPO\OTD\Models\TaskFieldValue;
use WPO\OTD\Repositories\BaseRepository;
use WPO\OTD\Repositories\RepositoryRegistry;
use WPO\OTD\Tests\TestCase;

class TaskFieldTest extends TestCase {
	/**
	 * @var Mockery\MockInterface
	 */
	private $wc_logger;

	protected function setUp(): void {
		parent::setUp();

		$this->set_static_property( Logger::class, 'wc_logger', null );
		$this->wc_logger = Mockery::mock( 'WC_Logger_Interface' );
		Functions\when( 'wc_get_logger' )->justReturn( $this->wc_logger );
	}

	protected function tearDown(): void {
		$this->set_static_property( Logger::class, 'wc_logger', null );
		$this->set_static_property( RepositoryRegistry::class, 'map', array() );

		parent::tearDown();
	}

	public function test_hydrates_from_a_database_row(): void {
		$field = new TaskField(
			array(
				'id'           => '5',
				'label'        => 'Due Date',
				'type'         => 'date',
				'slug'         => 'due_date',
				'is_required'  => '0',
				'is_editable'  => '0',
				'is_protected' => '1',
			)
		);

		$this->assertSame( 5, $field->id );
		$this->assertSame( 'Due Date', $field->label );
		$this->assertSame( 'date', $field->type );
		$this->assertSame( 'due_date', $field->slug );
		$this->assertFalse( $field->is_required );
		$this->assertFalse( $field->is_editable );
		$this->assertTrue( $field->is_protected );
	}

	public function test_defaults_for_a_new_field(): void {
		$field = new TaskField( array( 'label' => 'Notes', 'type' => 'text' ) );

		$this->assertSame( 0, $field->id );
		$this->assertFalse( $field->is_required );
		$this->assertTrue( $field->is_editable );
		$this->assertFalse( $field->is_protected );
	}

	public function test_slug_is_derived_from_the_label_with_underscores(): void {
		$this->assertSame( 'shipping_carrier', ( new TaskField( array( 'label' => 'Shipping Carrier', 'type' => 'text' ) ) )->slug );
	}

	public function test_a_given_slug_is_sanitized_the_same_way(): void {
		$this->assertSame( 'my_custom_slug', ( new TaskField( array( 'label' => 'Ignored', 'slug' => 'My Custom-Slug!', 'type' => 'text' ) ) )->slug );
	}

	public function test_an_unknown_type_falls_back_to_text_and_is_logged(): void {
		$this->wc_logger->shouldReceive( 'log' )->once()->with( 'error', 'Invalid field type "user" for field "creator". Defaulting to text.', Mockery::any() );

		$this->assertSame( 'text', ( new TaskField( array( 'label' => 'Creator', 'type' => 'user' ) ) )->type );
	}

	public function test_a_missing_type_falls_back_to_text(): void {
		$this->wc_logger->shouldReceive( 'log' )->once();

		$this->assertSame( 'text', ( new TaskField( array( 'label' => 'Notes' ) ) )->type );
	}

	public function test_fill_cannot_change_id_type_or_slug(): void {
		$field = new TaskField( array( 'id' => 9, 'label' => 'Notes', 'type' => 'text', 'slug' => 'notes' ) );

		$field->fill( array( 'id' => 1, 'type' => 'number', 'slug' => 'other', 'label' => 'Remarks', 'is_required' => true ) );

		$this->assertSame( 9, $field->id );
		$this->assertSame( 'text', $field->type );
		$this->assertSame( 'notes', $field->slug );
		$this->assertSame( 'Remarks', $field->label );
		$this->assertTrue( $field->is_required );
	}

	public function test_relations_are_loaded_by_field_id(): void {
		$values  = Mockery::mock( BaseRepository::class );
		$options = Mockery::mock( BaseRepository::class );
		$values->shouldReceive( 'find_all_by' )->once()->with( 'field_id', 9 )->andReturn( array( 'value' ) );
		$options->shouldReceive( 'find_all_by' )->once()->with( 'field_id', 9 )->andReturn( array( 'option' ) );
		RepositoryRegistry::register( TaskFieldValue::class, static fn() => $values );
		RepositoryRegistry::register( TaskFieldOption::class, static fn() => $options );

		$field = new TaskField( array( 'id' => 9, 'label' => 'Notes', 'type' => 'text' ) );

		$this->assertSame( array( 'value' ), $field->field_values() );
		$this->assertSame( array( 'option' ), $field->field_options() );
	}
}
