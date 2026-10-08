<?php

namespace WPO\OTD\Tests\Integration\Repositories;

use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Models\TaskFieldValue;
use WPO\OTD\Repositories\TaskFieldValueRepository;
use WPO\OTD\Tests\IntegrationTestCase;

class TaskFieldValueRepositoryTest extends IntegrationTestCase {
	private TaskFieldValueRepository $repository;
	private int $task_id;

	public function set_up() {
		parent::set_up();

		global $wpdb;

		$this->repository = new TaskFieldValueRepository();

		$wpdb->insert( $this->table( 'tasks' ), array( 'title' => 'Task' ) );
		$this->task_id = (int) $wpdb->insert_id;
	}

	public function test_find_by_task_and_field(): void {
		$this->repository->insert( array( 'task_id' => $this->task_id, 'field_id' => DefaultTaskFields::STATUS, 'value' => '2' ) );

		$value = $this->repository->find_by_task_and_field( $this->task_id, DefaultTaskFields::STATUS );

		$this->assertInstanceOf( TaskFieldValue::class, $value );
		$this->assertSame( '2', $value->value );
		$this->assertNull( $this->repository->find_by_task_and_field( $this->task_id, DefaultTaskFields::PRIORITY ) );
	}

	public function test_delete_by_task_and_field_only_removes_that_field(): void {
		$this->repository->update_task_multiple_field_values(
			$this->task_id,
			array(
				DefaultTaskFields::STATUS   => '2',
				DefaultTaskFields::PRIORITY => '5',
			)
		);

		$this->repository->delete_by_task_and_field( $this->task_id, DefaultTaskFields::PRIORITY );
		$this->repository->delete_by_task_and_field( $this->task_id, DefaultTaskFields::DUE_DATE );

		$this->assertSame( array( '2' ), $this->field_values( $this->task_id, DefaultTaskFields::STATUS ) );
		$this->assertSame( array(), $this->field_values( $this->task_id, DefaultTaskFields::PRIORITY ) );
	}

	public function test_updating_several_fields_replaces_only_those_fields(): void {
		$this->repository->update_task_multiple_field_values(
			$this->task_id,
			array(
				DefaultTaskFields::STATUS   => '1',
				DefaultTaskFields::PRIORITY => '4',
			)
		);

		$inserted = $this->repository->update_task_multiple_field_values(
			$this->task_id,
			array(
				DefaultTaskFields::STATUS   => '2',
				DefaultTaskFields::POSITION => 3.5,
			)
		);

		$this->assertSame( 2, $inserted );
		$this->assertSame( array( '2' ), $this->field_values( $this->task_id, DefaultTaskFields::STATUS ) );
		$this->assertSame( array( '3.5' ), $this->field_values( $this->task_id, DefaultTaskFields::POSITION ) );
		$this->assertSame( array( '4' ), $this->field_values( $this->task_id, DefaultTaskFields::PRIORITY ), 'Fields not passed are untouched.' );
	}

	public function test_an_array_stores_one_row_per_value(): void {
		$inserted = $this->repository->update_task_multiple_field_values( $this->task_id, array( DefaultTaskFields::ORDER => array( '11', '12' ) ) );

		$this->assertSame( 2, $inserted );
		$this->assertSame( array( '11', '12' ), $this->field_values( $this->task_id, DefaultTaskFields::ORDER ) );
	}

	public function test_null_deletes_without_reinserting(): void {
		$this->repository->update_task_multiple_field_values( $this->task_id, array( DefaultTaskFields::DONE_DATE => '2025-01-01 00:00:00' ) );

		$this->assertSame( 0, $this->repository->update_task_multiple_field_values( $this->task_id, array( DefaultTaskFields::DONE_DATE => null ) ) );
		$this->assertSame( array(), $this->field_values( $this->task_id, DefaultTaskFields::DONE_DATE ) );
	}

	public function test_nothing_to_update_returns_zero(): void {
		$this->assertSame( 0, $this->repository->update_task_multiple_field_values( $this->task_id, array() ) );
	}

	public function test_a_failed_insert_restores_the_deleted_values(): void {
		global $wpdb;

		$this->repository->update_task_multiple_field_values( $this->task_id, array( DefaultTaskFields::STATUS => '1' ) );

		// Field 999 does not exist, so its row violates the foreign key and the whole insert fails.
		$wpdb->suppress_errors( true );
		$result = $this->repository->update_task_multiple_field_values(
			$this->task_id,
			array(
				DefaultTaskFields::STATUS => '2',
				999                       => 'x',
			)
		);
		$wpdb->suppress_errors( false );

		$this->assertFalse( $result );
		$this->assertSame( array( '1' ), $this->field_values( $this->task_id, DefaultTaskFields::STATUS ) );
	}

	public function test_deleting_a_task_cascades_to_its_values(): void {
		global $wpdb;

		$this->repository->update_task_multiple_field_values( $this->task_id, array( DefaultTaskFields::STATUS => '1' ) );

		$wpdb->delete( $this->table( 'tasks' ), array( 'id' => $this->task_id ) );

		$this->assertSame( 0, $this->count_rows( 'task_field_values' ) );
	}
}
