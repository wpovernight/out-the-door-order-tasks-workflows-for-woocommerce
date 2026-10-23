<?php

namespace WPO\OTD\Tests\Integration\Services;

use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Enums\TaskStatusRoleSettingKeys;
use WPO\OTD\Services\TaskManagerService;
use WPO\OTD\Services\TaskManagerSettingsService;
use WPO\OTD\Tests\IntegrationTestCase;

/**
 * Moving tasks between and within status columns, finishing and archiving them.
 */
class TaskMovementTest extends IntegrationTestCase {
	private const REBALANCE_HOOK = 'wpo_otd_rebalance_task_positions';

	private TaskManagerService $service;
	private int $todo;
	private int $doing;
	private int $done;

	public function set_up() {
		parent::set_up();

		$this->service = $this->make( TaskManagerService::class );
		$this->todo    = $this->status_option_id( 'not_started' );
		$this->doing   = $this->status_option_id( 'in_progress' );
		$this->done    = $this->status_option_id( 'done' );
	}

	/** ================================
	 *   move_task()
	 *  ================================ */

	public function test_moving_to_the_top_of_an_empty_column(): void {
		$id = $this->create_task( $this->todo );

		$this->assertSame( 1.0, $this->service->move_task( $id, $this->doing ) );
		$this->assertSame( (string) $this->doing, $this->field_value( $id, DefaultTaskFields::STATUS ) );
		$this->assertSame( '1', $this->field_value( $id, DefaultTaskFields::POSITION ) );
	}

	public function test_moving_to_the_top_halves_the_first_position(): void {
		$first = $this->create_task( $this->doing );
		$id    = $this->create_task( $this->todo );

		$this->assertSame( 1.0, $this->position( $first ) );
		$this->assertSame( 0.5, $this->service->move_task( $id, $this->doing ) );
	}

	public function test_moving_to_the_top_above_a_later_position_takes_one(): void {
		$this->set_position( $this->create_task( $this->doing ), 4.0 );
		$id = $this->create_task( $this->todo );

		$this->assertSame( 1.0, $this->service->move_task( $id, $this->doing ) );
	}

	public function test_moving_between_two_tasks_takes_the_midpoint(): void {
		$first  = $this->create_task( $this->doing );
		$this->create_task( $this->doing );
		$id     = $this->create_task( $this->todo );

		$this->assertSame( 1.5, $this->service->move_task( $id, $this->doing, $first ) );
	}

	public function test_moving_after_the_last_task_adds_one(): void {
		$this->create_task( $this->doing );
		$last = $this->create_task( $this->doing );
		$id   = $this->create_task( $this->todo );

		$this->assertSame( 3.0, $this->service->move_task( $id, $this->doing, $last ) );
	}

	public function test_moving_to_the_end_of_a_column(): void {
		$this->create_task( $this->doing );
		$this->create_task( $this->doing );
		$id = $this->create_task( $this->todo );

		$this->assertSame( 3.0, $this->service->move_task( $id, $this->doing, null, 'last' ) );
		$this->assertSame( 1.0, $this->service->move_task( $this->create_task( $this->todo ), $this->done, null, 'last' ) );
	}

	public function test_reordering_within_a_column_ignores_the_moving_task(): void {
		$a = $this->create_task( $this->todo );
		$b = $this->create_task( $this->todo );
		$c = $this->create_task( $this->todo );

		// Move A between B and C.
		$this->assertSame( 2.5, $this->service->move_task( $a, $this->todo, $b ) );
		// Move C to the end: the last other task is now A at 2.5.
		$this->assertSame( 3.5, $this->service->move_task( $c, $this->todo, null, 'last' ) );
	}

	public function test_repeated_moves_into_the_same_gap_keep_halving(): void {
		$anchor = $this->create_task( $this->todo );
		$this->create_task( $this->todo );

		$positions = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$positions[] = $this->service->move_task( $this->create_task( $this->doing ), $this->todo, $anchor );
		}

		$this->assertSame( array( 1.5, 1.25, 1.125, 1.0625 ), $positions );
	}

	public function test_moving_reports_the_status_change(): void {
		$id    = $this->create_task( $this->todo );
		$fired = array();
		add_action(
			'wpo_otd_task_moved',
			static function ( ...$args ) use ( &$fired ) {
				$fired[] = $args;
			},
			10,
			4
		);

		$this->service->move_task( $id, $this->doing );

		$this->assertSame(
			array(
				array(
					$id,
					$this->doing,
					1.0,
					array(
						'status' => array(
							'old_value' => $this->todo,
							'new_value' => $this->doing,
						),
					),
				),
			),
			$fired
		);
	}

	public function test_the_new_position_can_be_filtered(): void {
		$id = $this->create_task( $this->todo );

		add_filter( 'wpo_otd_task_calculated_new_position', static fn( $position, $task_id, $previous, $status ) => 42.0, 10, 4 );

		$this->assertSame( 42.0, $this->service->move_task( $id, $this->doing ) );
		$this->assertSame( '42', $this->field_value( $id, DefaultTaskFields::POSITION ) );
	}

	public function test_moving_into_done_sets_the_done_date_and_moving_out_clears_it(): void {
		$id = $this->create_task( $this->doing );

		$this->service->move_task( $id, $this->done );
		$this->assert_done_now( $id );

		$this->service->move_task( $id, $this->todo );
		$this->assertNull( $this->field_value( $id, DefaultTaskFields::DONE_DATE ) );
	}

	public function test_extra_field_values_are_saved_and_win_over_the_done_date_logic(): void {
		$id = $this->create_task( $this->doing );

		$this->service->move_task(
			$id,
			$this->done,
			null,
			'first',
			array(
				DefaultTaskFields::DONE_DATE => '2020-01-01 00:00:00',
				DefaultTaskFields::ORDER     => '15',
			)
		);

		$this->assertSame( '2020-01-01 00:00:00', $this->field_value( $id, DefaultTaskFields::DONE_DATE ) );
		$this->assertSame( '15', $this->field_value( $id, DefaultTaskFields::ORDER ) );
	}

	public function test_without_a_done_role_every_move_clears_the_done_date(): void {
		$this->clear_done_role();
		$id = $this->create_task( $this->doing );
		$this->service->set_field_value( $id, DefaultTaskFields::DONE_DATE, '2020-01-01 00:00:00' );

		$this->service->move_task( $id, $this->done );

		$this->assertNull( $this->field_value( $id, DefaultTaskFields::DONE_DATE ) );
	}

	/**
	 * @dataProvider invalid_move_provider
	 */
	public function test_invalid_moves_are_rejected( string $placement, string $target, string $message ): void {
		$id     = $this->create_task( $this->todo );
		$target = 'priority' === $target ? $this->priority_option_id( 'low' ) : 999999;

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( $message );

		$this->service->move_task( $id, $target, null, $placement );
	}

	public function invalid_move_provider(): array {
		return array(
			'unknown placement'    => array( 'middle', 'priority', 'Invalid default position.' ),
			'option of a field'    => array( 'first', 'priority', 'Invalid target status ID.' ),
			'option that is gone'  => array( 'last', 'missing', 'Invalid target status ID.' ),
		);
	}

	public function test_moving_a_task_that_does_not_exist_is_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Task not found.' );

		try {
			$this->service->move_task( 999999, $this->doing );
		} finally {
			$this->assertSame( 0, did_action( 'wpo_otd_task_moved' ) );
		}
	}

	/** ================================
	 *   Rebalancing
	 *  ================================ */

	public function test_crowded_positions_schedule_a_rebalance_of_the_column(): void {
		$anchor = $this->create_task( $this->todo );
		$next   = $this->create_task( $this->todo );
		$this->set_position( $next, 1.00005 );

		$this->service->move_task( $this->create_task( $this->doing ), $this->todo, $anchor );

		$this->assertTrue( as_has_scheduled_action( self::REBALANCE_HOOK, array( 'status_id' => $this->todo ), 'wpo_otd' ) );
	}

	public function test_roomy_positions_do_not_schedule_a_rebalance(): void {
		$anchor = $this->create_task( $this->todo );
		$this->create_task( $this->todo );

		$this->service->move_task( $this->create_task( $this->doing ), $this->todo, $anchor );

		$this->assertFalse( as_has_scheduled_action( self::REBALANCE_HOOK, null, 'wpo_otd' ) );
	}

	public function test_the_precision_threshold_is_filterable(): void {
		add_filter( 'wpo_otd_task_position_precision_threshold', static fn() => 5.0 );
		$anchor = $this->create_task( $this->todo );
		$this->create_task( $this->todo );

		$this->service->move_task( $this->create_task( $this->doing ), $this->todo, $anchor );

		$this->assertTrue( as_has_scheduled_action( self::REBALANCE_HOOK, array( 'status_id' => $this->todo ), 'wpo_otd' ) );
	}

	public function test_a_rebalance_is_scheduled_once_per_column_at_the_filtered_time(): void {
		$at = time() + 600;
		add_filter( 'wpo_otd_rebalance_task_positions_scheduled_time', static fn() => $at );

		$this->service->schedule_rebalance_task_job( $this->todo );
		$this->service->schedule_rebalance_task_job( $this->todo );
		$this->service->schedule_rebalance_task_job( $this->doing );

		$this->assertSame( $at, as_next_scheduled_action( self::REBALANCE_HOOK, array( 'status_id' => $this->todo ), 'wpo_otd' ) );
		$this->assertCount( 1, as_get_scheduled_actions( array( 'hook' => self::REBALANCE_HOOK, 'args' => array( 'status_id' => $this->todo ), 'status' => 'pending' ), 'ids' ) );
		$this->assertCount( 2, as_get_scheduled_actions( array( 'hook' => self::REBALANCE_HOOK, 'status' => 'pending' ), 'ids' ) );
	}

	public function test_an_invalid_status_is_not_scheduled(): void {
		$this->service->schedule_rebalance_task_job( 0 );

		$this->assertFalse( as_has_scheduled_action( self::REBALANCE_HOOK ) );
	}

	public function test_the_scheduled_action_rebalances_the_column(): void {
		$ids = array( $this->create_task( $this->todo ), $this->create_task( $this->todo ) );
		$this->set_position( $ids[0], 1.25 );
		$this->set_position( $ids[1], 1.5 );

		do_action( self::REBALANCE_HOOK, $this->todo );

		$this->assertSame( array( 1.0, 2.0 ), array_map( array( $this, 'position' ), $ids ) );
	}

	/** ================================
	 *   mark_task_finished()
	 *  ================================ */

	public function test_finishing_moves_the_task_to_the_end_of_the_done_column(): void {
		$this->create_task( $this->done );
		$id = $this->create_task( $this->doing );

		$this->assertTrue( $this->service->mark_task_finished( $id ) );

		$this->assertSame( (string) $this->done, $this->field_value( $id, DefaultTaskFields::STATUS ) );
		$this->assertSame( 2.0, $this->position( $id ) );
		$this->assertNotNull( $this->field_value( $id, DefaultTaskFields::DONE_DATE ) );
		$this->assertSame( 1, did_action( 'wpo_otd_task_marked_finished' ) );
	}

	public function test_a_move_whose_field_values_cannot_be_saved_fails_without_announcing_it(): void {
		$id = $this->create_task( $this->todo );
		$this->fail_queries( '/^INSERT INTO \S*task_field_values /' );

		try {
			$this->service->move_task( $id, $this->doing );
			$this->fail( 'The move must fail.' );
		} catch ( \Exception $e ) {
			$this->assertNotInstanceOf( \RuntimeException::class, $e, 'A database failure is a server error, not a conflict to show.' );
			$this->assertStringContainsString( "Failed to save the task's field values.", $e->getMessage() );
		}

		$this->assertSame( 0, did_action( 'wpo_otd_task_moved' ) );
		$this->assertSame( (string) $this->todo, $this->field_value( $id, DefaultTaskFields::STATUS ) );
	}

	public function test_finishing_a_task_whose_field_values_cannot_be_saved_fails_without_announcing_it(): void {
		$id = $this->create_task( $this->doing );
		$this->fail_queries( '/^INSERT INTO \S*task_field_values /' );

		try {
			$this->service->mark_task_finished( $id );
			$this->fail( 'Finishing must fail.' );
		} catch ( \Exception $e ) {
			$this->assertNotInstanceOf( \RuntimeException::class, $e, 'The REST API shows a RuntimeException to the user as a conflict.' );
		}

		$this->assertSame( 0, did_action( 'wpo_otd_task_marked_finished' ) );
	}

	public function test_finishing_a_missing_task_is_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );

		$this->service->mark_task_finished( 999999 );
	}

	public function test_finishing_needs_a_done_role(): void {
		$this->clear_done_role();
		$id = $this->create_task( $this->doing );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'No option is assigned to the "done" role.' );

		$this->service->mark_task_finished( $id );
	}

	public function test_finishing_needs_the_done_option_to_still_exist(): void {
		$settings                                  = get_option( TaskManagerSettingsService::OPTION_NAME );
		$settings[ TaskStatusRoleSettingKeys::DONE ] = 999999;
		update_option( TaskManagerSettingsService::OPTION_NAME, $settings );
		$id = $this->create_task( $this->doing );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( '(ID 999999) no longer exists.' );

		$this->service->mark_task_finished( $id );
	}

	/** ================================
	 *   archive_task() / unarchive_task()
	 *  ================================ */

	public function test_archiving_stamps_the_date_once_and_keeps_the_status(): void {
		$id = $this->create_task( $this->doing );

		$this->assertTrue( $this->service->archive_task( $id ) );
		$this->service->set_field_value( $id, DefaultTaskFields::ARCHIVED_DATE, '2020-01-01 00:00:00' );
		$this->assertTrue( $this->service->archive_task( $id ) );

		$this->assertSame( array( '2020-01-01 00:00:00' ), $this->field_values( $id, DefaultTaskFields::ARCHIVED_DATE ), 'Archiving again keeps the first date.' );
		$this->assertSame( (string) $this->doing, $this->field_value( $id, DefaultTaskFields::STATUS ) );
		$this->assertSame( 2, did_action( 'wpo_otd_task_archived' ) );
	}

	public function test_unarchiving_clears_the_date(): void {
		$id = $this->create_task( $this->doing );
		$this->service->archive_task( $id );

		$this->assertTrue( $this->service->unarchive_task( $id ) );
		$this->assertTrue( $this->service->unarchive_task( $id ), 'Unarchiving an active task is harmless.' );

		$this->assertNull( $this->field_value( $id, DefaultTaskFields::ARCHIVED_DATE ) );
		$this->assertSame( 2, did_action( 'wpo_otd_task_unarchived' ) );
	}

	public function test_archiving_or_unarchiving_a_missing_task_is_rejected(): void {
		foreach ( array( 'archive_task', 'unarchive_task' ) as $method ) {
			try {
				$this->service->$method( 999999 );
				$this->fail( "{$method}() must reject a missing task." );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertSame( 'Task not found.', $e->getMessage() );
			}
		}
	}

	/**
	 * Create a task at the end of a status column.
	 *
	 * @param int $status_id
	 *
	 * @return int
	 */
	private function create_task( int $status_id ): int {
		return $this->service->create_task(
			'Task',
			'',
			array(
				array(
					'field_id'   => DefaultTaskFields::STATUS,
					'field_slug' => 'status',
					'value'      => $status_id,
				),
			)
		)['id'];
	}

	/**
	 * Overwrite a task's stored position.
	 *
	 * @param int   $task_id
	 * @param float $position
	 *
	 * @return void
	 */
	private function set_position( int $task_id, float $position ): void {
		$this->service->set_field_value( $task_id, DefaultTaskFields::POSITION, (string) $position );
	}

	/**
	 * Get a task's stored position.
	 *
	 * @param int $task_id
	 *
	 * @return float
	 */
	public function position( int $task_id ): float {
		return (float) $this->field_value( $task_id, DefaultTaskFields::POSITION );
	}

	/**
	 * Unassign the "done" role.
	 *
	 * @return void
	 */
	private function clear_done_role(): void {
		$settings                                  = get_option( TaskManagerSettingsService::OPTION_NAME );
		$settings[ TaskStatusRoleSettingKeys::DONE ] = null;
		update_option( TaskManagerSettingsService::OPTION_NAME, $settings );
	}
}
