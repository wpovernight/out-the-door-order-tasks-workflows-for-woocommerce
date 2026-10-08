<?php

namespace WPO\OTD\Tests\Unit\Models;

use DateTime;
use Mockery;
use WPO\OTD\Models\Task;
use WPO\OTD\Models\TaskFieldValue;
use WPO\OTD\Repositories\BaseRepository;
use WPO\OTD\Repositories\RepositoryRegistry;
use WPO\OTD\Tests\TestCase;

class TaskTest extends TestCase {
	protected function tearDown(): void {
		$this->set_static_property( RepositoryRegistry::class, 'map', array() );

		parent::tearDown();
	}

	public function test_hydrates_from_a_database_row(): void {
		$task = new Task(
			array(
				'id'          => '12',
				'title'       => 'Pack order',
				'description' => 'Fragile',
				'created_at'  => '2025-01-02 03:04:05',
				'updated_at'  => '2025-01-03 03:04:05',
			)
		);

		$this->assertSame( 12, $task->id );
		$this->assertSame( 'Pack order', $task->title );
		$this->assertSame( 'Fragile', $task->description );
		$this->assertSame( '2025-01-02 03:04:05', $task->created_at->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( '2025-01-03 03:04:05', $task->updated_at->format( 'Y-m-d H:i:s' ) );
	}

	public function test_defaults_for_a_new_task(): void {
		$before = new DateTime( '-1 second' );
		$task   = new Task( array( 'title' => 'New' ) );

		$this->assertSame( 0, $task->id );
		$this->assertSame( '', $task->description );
		$this->assertGreaterThanOrEqual( $before, $task->created_at );
		$this->assertGreaterThanOrEqual( $before, $task->updated_at );
	}

	/**
	 * @dataProvider invalid_id_provider
	 */
	public function test_non_positive_ids_become_zero( $id ): void {
		$this->assertSame( 0, ( new Task( array( 'id' => $id, 'title' => 'T' ) ) )->id );
	}

	public function invalid_id_provider(): array {
		return array(
			'zero'     => array( 0 ),
			'negative' => array( -4 ),
			'null'     => array( null ),
		);
	}

	public function test_to_db_array_holds_the_writable_columns(): void {
		$new = new Task( array( 'title' => 'T', 'description' => 'D', 'created_at' => '2025-01-02 03:04:05' ) );

		$this->assertSame(
			array(
				'title'       => 'T',
				'description' => 'D',
				'created_at'  => '2025-01-02 03:04:05',
			),
			$new->to_db_array()
		);

		$existing = new Task( array( 'id' => 4, 'title' => 'T', 'description' => 'D' ) );

		$this->assertSame( array( 'title' => 'T', 'description' => 'D' ), $existing->to_db_array() );
	}

	public function test_field_values_are_loaded_by_task_id(): void {
		$values     = array( new TaskFieldValue( array( 'task_id' => 4, 'field_id' => 1, 'value' => '1' ) ) );
		$repository = Mockery::mock( BaseRepository::class );
		$repository->shouldReceive( 'find_all_by' )->once()->with( 'task_id', 4 )->andReturn( $values );
		RepositoryRegistry::register( TaskFieldValue::class, static fn() => $repository );

		$this->assertSame( $values, ( new Task( array( 'id' => 4, 'title' => 'T' ) ) )->field_values() );
	}
}
