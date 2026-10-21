<?php

namespace WPO\OTD\Tests\Integration\Repositories;

use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Repositories\TaskFieldValueRepository;
use WPO\OTD\Repositories\TaskRepository;
use WPO\OTD\Tests\IntegrationTestCase;

/**
 * Position lookups and rebalancing within a status column.
 */
class TaskRepositoryTest extends IntegrationTestCase {
	private TaskRepository $repository;
	private TaskFieldValueRepository $values;
	private int $todo;
	private int $doing;

	public function set_up() {
		parent::set_up();

		$this->values     = new TaskFieldValueRepository();
		$this->repository = new TaskRepository( $this->values );
		$this->todo       = $this->status_option_id( 'not_started' );
		$this->doing      = $this->status_option_id( 'in_progress' );
	}

	/** ================================
	 *   fit_to_column()
	 *  ================================ */

	public function test_a_text_is_fitted_to_its_column(): void {
		$this->assertSame( str_repeat( 'é', 255 ), $this->repository->fit_to_column( 'title', str_repeat( 'é', 300 ) ) );
		$this->assertSame( 'Fits', $this->repository->fit_to_column( 'title', 'Fits' ) );
	}

	public function test_a_text_is_kept_as_given_when_the_columns_charset_cannot_be_read(): void {
		add_filter( 'pre_get_col_charset', static fn() => new \WP_Error( 'wpdb_get_table_charset_failure', 'No charset.' ) );

		$this->assertSame( str_repeat( 'é', 300 ), $this->repository->fit_to_column( 'title', str_repeat( 'é', 300 ) ) );
	}

	/** ================================
	 *   get_last_task_position()
	 *  ================================ */

	public function test_last_position_of_an_empty_status_is_null(): void {
		$this->assertNull( $this->repository->get_last_task_position( null, $this->todo ) );
	}

	public function test_last_position_is_the_numeric_maximum_of_the_status(): void {
		$this->create_tasks( array( 2.0, 10.0, 9.5 ), $this->todo );
		$this->create_tasks( array( 50.0 ), $this->doing );

		// Compared as numbers, not strings: "10" sorts before "9.5" as text.
		$this->assertSame( 10.0, $this->repository->get_last_task_position( null, $this->todo ) );
	}

	public function test_last_position_can_exclude_a_task(): void {
		$ids = $this->create_tasks( array( 1.0, 2.0, 3.0 ), $this->todo );

		$this->assertSame( 2.0, $this->repository->get_last_task_position( $ids[2], $this->todo ) );
	}

	/** ================================
	 *   get_next_task_position()
	 *  ================================ */

	public function test_next_position_needs_a_task_or_a_position(): void {
		$this->expectException( \InvalidArgumentException::class );

		$this->repository->get_next_task_position( null, $this->todo );
	}

	public function test_next_position_after_a_given_position(): void {
		$this->create_tasks( array( 1.0, 2.0, 3.0 ), $this->todo );

		$this->assertSame( 3.0, $this->repository->get_next_task_position( null, $this->todo, 2.0 ) );
		$this->assertSame( 2.0, $this->repository->get_next_task_position( null, $this->todo, 1.5 ) );
	}

	public function test_next_position_after_a_given_task_reads_its_position(): void {
		$ids = $this->create_tasks( array( 1.0, 4.0, 7.0 ), $this->todo );

		$this->assertSame( 7.0, $this->repository->get_next_task_position( $ids[1], $this->todo ) );
	}

	public function test_next_position_at_the_end_of_the_column_is_null(): void {
		$ids = $this->create_tasks( array( 1.0, 2.0 ), $this->todo );

		$this->assertNull( $this->repository->get_next_task_position( $ids[1], $this->todo ) );
	}

	public function test_next_position_skips_the_task_being_moved(): void {
		$ids = $this->create_tasks( array( 1.0, 2.0, 3.0 ), $this->todo );

		$this->assertSame( 3.0, $this->repository->get_next_task_position( $ids[0], $this->todo, null, $ids[1] ) );
	}

	public function test_a_zero_or_non_finite_position_means_the_start_of_the_column(): void {
		$this->create_tasks( array( 2.0, 3.0 ), $this->todo );

		$this->assertSame( 2.0, $this->repository->get_next_task_position( null, $this->todo, 0.0 ) );
		$this->assertSame( 2.0, $this->repository->get_next_task_position( null, $this->todo, INF ) );
		$this->assertSame( 2.0, $this->repository->get_next_task_position( null, $this->todo, NAN ) );
	}

	public function test_next_position_only_looks_in_the_target_status(): void {
		$this->create_tasks( array( 1.0, 5.0 ), $this->todo );
		$this->create_tasks( array( 2.0 ), $this->doing );

		$this->assertSame( 5.0, $this->repository->get_next_task_position( null, $this->todo, 1.0 ) );
	}

	/** ================================
	 *   rebalance_positions()
	 *  ================================ */

	public function test_rebalancing_renumbers_positions_from_one_in_order(): void {
		$ids = $this->create_tasks( array( 5.0, 2.0, 8.0, 1.0, 3.0 ), $this->todo );

		$this->assertTrue( $this->repository->rebalance_positions( $this->todo ) );

		$this->assertSame( array( 4.0, 2.0, 5.0, 1.0, 3.0 ), $this->positions( $ids ) );
	}

	public function test_rebalancing_normalizes_fractional_positions(): void {
		$ids = $this->create_tasks( array( 1.0, 1.5, 1.75, 1.875, 2.0 ), $this->todo );

		$this->repository->rebalance_positions( $this->todo );

		$this->assertSame( array( 1.0, 2.0, 3.0, 4.0, 5.0 ), $this->positions( $ids ) );
	}

	public function test_rebalancing_separates_duplicate_positions(): void {
		$ids = $this->create_tasks( array( 2.0, 1.0, 2.0, 1.0 ), $this->todo );

		$this->repository->rebalance_positions( $this->todo );

		$positions = $this->positions( $ids );
		$this->assertEqualsCanonicalizing( array( 1.0, 2.0, 3.0, 4.0 ), $positions );
		$this->assertLessThan( min( $positions[0], $positions[2] ), max( $positions[1], $positions[3] ), 'Tasks that were at 1 stay ahead of those at 2.' );
	}

	public function test_rebalancing_handles_zero_negative_and_large_positions(): void {
		$ids = $this->create_tasks( array( 1000000.0, -5.0, 0.0, 0.00001 ), $this->todo );

		$this->repository->rebalance_positions( $this->todo );

		$this->assertSame( array( 4.0, 1.0, 2.0, 3.0 ), $this->positions( $ids ) );
	}

	public function test_rebalancing_leaves_other_statuses_alone(): void {
		$todo  = $this->create_tasks( array( 1.5, 1.75 ), $this->todo );
		$doing = $this->create_tasks( array( 2.5, 2.75 ), $this->doing );

		$this->repository->rebalance_positions( $this->todo );

		$this->assertSame( array( 1.0, 2.0 ), $this->positions( $todo ) );
		$this->assertSame( array( 2.5, 2.75 ), $this->positions( $doing ) );
	}

	public function test_rebalancing_an_already_balanced_or_empty_status_succeeds(): void {
		$ids = $this->create_tasks( array( 1.0, 2.0 ), $this->todo );

		$this->assertTrue( $this->repository->rebalance_positions( $this->todo ) );
		$this->assertSame( array( 1.0, 2.0 ), $this->positions( $ids ) );
		$this->assertTrue( $this->repository->rebalance_positions( $this->doing ) );
	}

	/**
	 * Create one task per position, all in the given status.
	 *
	 * @param float[] $positions
	 * @param int     $status_id
	 *
	 * @return int[] Task IDs, in the order of $positions.
	 */
	private function create_tasks( array $positions, int $status_id ): array {
		$ids = array();

		foreach ( $positions as $index => $position ) {
			$id = $this->repository->insert( array( 'title' => "Task {$index}" ) );

			$this->values->update_task_multiple_field_values(
				$id,
				array(
					DefaultTaskFields::STATUS   => $status_id,
					DefaultTaskFields::POSITION => $position,
				)
			);

			$ids[] = $id;
		}

		return $ids;
	}

	/**
	 * Get the stored positions of tasks, as floats.
	 *
	 * @param int[] $task_ids
	 *
	 * @return float[]
	 */
	private function positions( array $task_ids ): array {
		return array_map( fn( $id ) => (float) $this->field_value( $id, DefaultTaskFields::POSITION ), $task_ids );
	}
}
