<?php

namespace WPO\OTD\Tests\Unit\Models;

use Mockery;
use WPO\OTD\Models\Task;
use WPO\OTD\Models\TaskField;
use WPO\OTD\Models\TaskFieldValue;
use WPO\OTD\Repositories\BaseRepository;
use WPO\OTD\Repositories\RepositoryRegistry;
use WPO\OTD\Tests\TestCase;

class TaskFieldValueTest extends TestCase {
	protected function tearDown(): void {
		$this->set_static_property( RepositoryRegistry::class, 'map', array() );

		parent::tearDown();
	}

	public function test_hydrates_from_a_database_row(): void {
		$value = new TaskFieldValue( array( 'id' => '10', 'task_id' => '4', 'field_id' => '2', 'value' => '1.5' ) );

		$this->assertSame( 10, $value->id );
		$this->assertSame( 4, $value->task_id );
		$this->assertSame( 2, $value->field_id );
		$this->assertSame( '1.5', $value->value );
	}

	public function test_value_is_nullable(): void {
		$this->assertNull( ( new TaskFieldValue( array( 'task_id' => 4, 'field_id' => 6 ) ) )->value );
		$this->assertNull( ( new TaskFieldValue( array( 'task_id' => 4, 'field_id' => 6, 'value' => null ) ) )->value );
	}

	public function test_falsy_values_are_kept_as_is(): void {
		$this->assertSame( '0', ( new TaskFieldValue( array( 'task_id' => 4, 'field_id' => 6, 'value' => '0' ) ) )->value );
		$this->assertSame( '', ( new TaskFieldValue( array( 'task_id' => 4, 'field_id' => 6, 'value' => '' ) ) )->value );
	}

	public function test_ids_are_made_absolute(): void {
		$value = new TaskFieldValue( array( 'id' => -1, 'task_id' => -4, 'field_id' => -2 ) );

		$this->assertSame( 0, $value->id );
		$this->assertSame( 4, $value->task_id );
		$this->assertSame( 2, $value->field_id );
	}

	public function test_task_and_field_are_loaded_by_their_foreign_keys(): void {
		$task       = new Task( array( 'id' => 4, 'title' => 'T' ) );
		$field      = new TaskField( array( 'id' => 2, 'label' => 'Position', 'type' => 'number' ) );
		$tasks      = Mockery::mock( BaseRepository::class );
		$fields     = Mockery::mock( BaseRepository::class );
		$tasks->shouldReceive( 'find_by' )->once()->with( 'id', 4 )->andReturn( $task );
		$fields->shouldReceive( 'find_by' )->once()->with( 'id', 2 )->andReturn( $field );
		RepositoryRegistry::register( Task::class, static fn() => $tasks );
		RepositoryRegistry::register( TaskField::class, static fn() => $fields );

		$value = new TaskFieldValue( array( 'task_id' => 4, 'field_id' => 2, 'value' => '1' ) );

		$this->assertSame( $task, $value->task() );
		$this->assertSame( $field, $value->field() );
	}
}
