<?php

namespace WPO\OTD\Tests\Integration\Services;

use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Repositories\TaskRepository;
use WPO\OTD\Services\CustomOrderStatusService;
use WPO\OTD\Services\TaskManagerService;
use WPO\OTD\Tests\IntegrationTestCase;

/**
 * What the services do when the database rejects a write.
 */
class DatabaseFailureTest extends IntegrationTestCase {
	private TaskManagerService $tasks;

	/**
	 * Log lines captured through the wpo_otd_logging_enabled filter, as "level: message".
	 *
	 * @var string[]
	 */
	private array $logs = array();

	public function set_up() {
		parent::set_up();

		$this->tasks = $this->make( TaskManagerService::class );
		$this->logs  = array();

		// Capture log lines instead of writing them to WooCommerce's log.
		add_filter(
			'wpo_otd_logging_enabled',
			function ( $enabled, $level, $message ) {
				$this->logs[] = "{$level}: {$message}";

				return false;
			},
			10,
			3
		);
	}

	public function test_a_rejected_task_insert_reports_the_database_error(): void {
		$this->fail_queries( '/^INSERT INTO `\w+wpo_otd_tasks`/' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Failed to create task. Database error: ' );

		$this->tasks->create_task( 'Rejected', '' );
	}

	public function test_a_rejected_task_update_reports_the_database_error(): void {
		$id = $this->create_task();
		$this->fail_queries( '/^UPDATE `\w+wpo_otd_tasks`/' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to update task. Database error: ' );

		$this->tasks->update_task( $id, array( 'title' => 'Rejected' ) );
	}

	public function test_a_rejected_field_insert_or_update_is_logged(): void {
		$field_id = $this->tasks->create_field( array( 'label' => 'Carrier', 'type' => 'text' ) );
		$this->fail_queries( '/^(INSERT INTO|UPDATE) `\w+wpo_otd_task_fields`/' );

		$this->assertFalse( $this->tasks->create_field( array( 'label' => 'Rejected', 'type' => 'text' ) ) );
		$this->assertFalse( $this->tasks->update_field( $field_id, array( 'label' => 'Rejected' ) ) );

		$this->assertSame( 1, did_action( 'wpo_otd_field_created' ) );
		$this->assertSame( 0, did_action( 'wpo_otd_field_updated' ) );
		$this->assertCount( 2, $this->logs );
		$this->assertStringStartsWith( 'error: Failed to create task field. Database error: ', $this->logs[0] );
		$this->assertStringStartsWith( "error: Failed to update task field {$field_id}. Database error: ", $this->logs[1] );
	}

	public function test_a_rejected_option_insert_undoes_the_position_shift(): void {
		$this->fail_queries( '/^INSERT INTO `\w+wpo_otd_task_field_options`/' );

		try {
			$this->tasks->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Review', 'position' => 2 ) );
			$this->fail( 'The failed insert must be reported.' );
		} catch ( \RuntimeException $e ) {
			// The ROLLBACK after the failed insert must not erase the reason.
			$this->assertStringStartsWith( 'Failed to create field option. Database error: ', $e->getMessage() );
			$this->assertStringContainsString( 'wpo_otd_forced_failure', $e->getMessage() );
		}

		$this->assertSame(
			array( 1, 2, 3 ),
			array_map( static fn( $option ) => $option->position, $this->tasks->get_field_options_by_field_id( DefaultTaskFields::STATUS ) ),
			'Shifting the existing options and inserting the new one happen together or not at all.'
		);
	}

	public function test_a_rejected_option_update_reports_the_database_error(): void {
		$option = $this->tasks->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Review' ) );
		$this->fail_queries( '/^UPDATE `\w+wpo_otd_task_field_options`/' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to persist field option. Database error: ' );

		$this->tasks->update_field_option( DefaultTaskFields::STATUS, $option['id'], array( 'label' => 'Rejected' ) );
	}

	public function test_a_rejected_value_update_is_logged(): void {
		$id = $this->create_task();
		$this->tasks->set_field_value( $id, DefaultTaskFields::ORDER, 10 );
		$this->fail_queries( '/^UPDATE `\w+wpo_otd_task_field_values`/' );

		$this->assertFalse( $this->tasks->set_field_value( $id, DefaultTaskFields::ORDER, 11 ) );
		$this->assertStringStartsWith( "error: Failed to persist field value for task {$id}, field 4. Database error: ", $this->logs[0] );
	}

	public function test_a_failed_lock_aborts_the_rebalance(): void {
		$this->create_task();
		$this->fail_queries( '/FOR UPDATE/' );

		$this->assertFalse( $this->make( TaskRepository::class )->rebalance_positions( $this->status_option_id( 'not_started' ) ) );
	}

	public function test_a_crashing_rebalance_is_logged_not_thrown(): void {
		$id = $this->create_task();
		$this->tasks->set_field_value( $id, DefaultTaskFields::POSITION, '3.5' );
		$this->throw_on_queries( '/FOR UPDATE/', new \RuntimeException( 'Lock wait timeout' ) );

		$this->tasks->rebalance_task_positions( $this->status_option_id( 'not_started' ) );

		$this->assertSame( '3.5', $this->field_value( $id, DefaultTaskFields::POSITION ) );
		$this->assertSame(
			array( 'error: Failed to rebalance task positions for status ' . $this->status_option_id( 'not_started' ) . ': Lock wait timeout' ),
			$this->logs
		);
	}

	public function test_rejected_custom_status_writes_report_the_database_error(): void {
		$service = new CustomOrderStatusService();
		$status  = $service->create( array( 'label' => 'Packed' ) );
		$this->fail_queries( '/^(INSERT INTO|UPDATE) `\w+wpo_otd_custom_statuses`/' );

		try {
			$service->create( array( 'label' => 'Rejected' ) );
			$this->fail( 'The failed insert must be reported.' );
		} catch ( \Exception $e ) {
			$this->assertStringStartsWith( 'Failed to create custom order status Database error: ', $e->getMessage() );
		}

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to update custom order status Database error: ' );

		$service->update( $status->id, array( 'label' => 'Rejected' ) );
	}

	/**
	 * Create a task that is not started yet.
	 *
	 * @return int
	 */
	private function create_task(): int {
		return $this->tasks->create_task(
			'Task',
			'',
			array(
				array(
					'field_id'   => DefaultTaskFields::STATUS,
					'field_slug' => 'status',
					'value'      => $this->status_option_id( 'not_started' ),
				),
			)
		)['id'];
	}
}
