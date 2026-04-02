<?php

namespace WPO\AOM\Repositories;

use InvalidArgumentException;
use WPO\AOM\Enums\DefaultTaskFields;
use WPO\AOM\Models\Task;
use WPO\AOM\Models\TaskFieldValue;

defined( 'ABSPATH' ) || exit;

class TaskRepository extends BaseRepository {
	private static string $table_name = 'tasks';
	private static string $model_class = Task::class;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( self::$table_name, self::$model_class );
	}

	/**
	 * Get the position of the next task based on the given task ID.
	 *
	 * @param int|null   $given_task_id       The ID of the reference task.
	 * @param int        $target_status_id    The status ID to filter tasks.
	 * @param float|null $given_task_position The position of the reference task. (Optional if given_task_id is provided)
	 * @param int|null   $moving_task_id      The ID of the task being moved (to exclude from results)
	 *
	 * @return float|null
	 * @throws InvalidArgumentException
	 */
	public function get_next_task_position(
		?int $given_task_id,
		int $target_status_id,
		?float $given_task_position = null,
		?int $moving_task_id = null
	): ?float {
		if ( empty( $given_task_id ) && is_null( $given_task_position ) ) {
			throw new InvalidArgumentException( 'Either given_task_id or given_task_position must be provided.' );
		}

		$task_field_value_repository = RepositoryRegistry::get( TaskFieldValue::class );
		$task_field_value_table_name = $task_field_value_repository->get_table_full_name();

		if ( $given_task_id && empty( $given_task_position ) ) {
			$given_task_field_value = $task_field_value_repository
				->find_by_task_and_field( $given_task_id, DefaultTaskFields::POSITION );
			$given_task_position    = $given_task_field_value ? (float) $given_task_field_value->value : 0.0;
		}

		// Ensure the given task position is a finite number, defaulting to 0 if not.
		if ( ! is_finite( $given_task_position ) ) {
			$given_task_position = 0.0;
		}

		$query = $task_field_value_repository
			->select( array( 'position.*' ) )
			->alias( 'position' )
			->join( "{$task_field_value_table_name} AS status", 'position.task_id', '=', 'status.task_id' )
			->where( 'status.field_id', DefaultTaskFields::STATUS )
			->where( 'status.value', $target_status_id )
			->where( 'position.field_id', DefaultTaskFields::POSITION );

		// Exclude the given task ID if provided.
		if ( ! empty( $given_task_id ) ) {
			$query->where( 'position.task_id', '!=', $given_task_id );
		}

		// Filter for positions greater than the given task position.
		// Positions always start at 1.0 or higher. So using `empty()` is fine.
		if ( ! empty( $given_task_position ) ) {
			$query->where_raw(
				$this->wpdb->prepare(
					'CAST(position.value AS DECIMAL(10,5)) > %f',
					$given_task_position
				)
			);
		}

		// Exclude the moving task ID if provided.
		if ( $moving_task_id ) {
			$query->where( 'position.task_id', '!=', $moving_task_id );
		}

		// Order by position ascending to get the next task.
		$next_task_value_field = $query
			->order_by_raw( 'CAST(position.value AS DECIMAL(10,5)) ASC' )
			->first();

		return $next_task_value_field ? (float) $next_task_value_field->value : null;
	}

	/**
	 * Get the position of the last task in a given status.
	 *
	 * @param int|null $given_task_id    The ID of the reference task to exclude.
	 * @param int      $target_status_id The status ID to filter tasks.
	 *
	 * @return float|null
	 */
	public function get_last_task_position(
		?int $given_task_id,
		int $target_status_id
	): ?float {
		$task_field_value_repository = RepositoryRegistry::get( TaskFieldValue::class );
		$task_field_value_table_name = $task_field_value_repository->get_table_full_name();

		$last_task_value_field = $task_field_value_repository
			->select( array( 'position.*' ) )
			->alias( 'position' )
			->join( "{$task_field_value_table_name} AS status", 'position.task_id', '=', 'status.task_id' )
			->where( 'status.field_id', DefaultTaskFields::STATUS )
			->where( 'status.value', $target_status_id )
			->where( 'position.field_id', DefaultTaskFields::POSITION )
			->where( 'position.task_id', '!=', $given_task_id ?? 0 )
			->order_by_raw( 'CAST(position.value AS DECIMAL(10,5)) DESC' )
			->first();

		return $last_task_value_field ? (float) $last_task_value_field->value : null;
	}

	/**
	 * Rebalance positions of tasks, optionally within a specific status.
	 *
	 * @param int $status_id
	 *
	 * @return bool
	 * @throws \Throwable
	 */
	public function rebalance_positions( int $status_id ): bool {
		return (bool) $this->transaction( function () use ( $status_id ) {
			$task_field_value_repository = RepositoryRegistry::get( TaskFieldValue::class );
			$task_field_value_table_name = $task_field_value_repository->get_table_full_name();

			$status_field_id   = DefaultTaskFields::STATUS;
			$position_field_id = DefaultTaskFields::POSITION;

			// Lock the relevant rows (prevents concurrent updates to these rows until COMMIT).
			$lock_query = "
				SELECT
					position.id
				FROM
					{$task_field_value_table_name} AS position
					INNER JOIN {$task_field_value_table_name} AS status ON position.task_id = status.task_id
				WHERE
					status.field_id = %d
					AND position.field_id = %d
					AND status.value = %d
					FOR UPDATE;
				";

			$locked = $task_field_value_repository
				->execute_raw( $lock_query, array( $status_field_id, $position_field_id, $status_id ) );

			// If lock query failed (not 0 rows, but actual failure), return false.
			if ( false === $locked ) {
				return false;
			}

			// If no rows found (empty status), return true (success - nothing to rebalance).
			if ( 0 === $locked ) {
				return true;
			}

			// CTE to rank and update positions.
			$ranked_cte = "
			WITH ranked AS (
				SELECT
					position.id,
					ROW_NUMBER() OVER (
						ORDER BY
							CAST(position.value AS DECIMAL(10, 5))
					) AS new_position
				FROM
					{$task_field_value_table_name} AS position
					INNER JOIN {$task_field_value_table_name} AS status ON position.task_id = status.task_id
				WHERE
					status.field_id = %d
					AND position.field_id = %d
					AND status.value = %d
			)
			UPDATE
				{$task_field_value_table_name} AS p
				INNER JOIN ranked r ON p.id = r.id
			SET
				p.value = r.new_position;
		";

			$affected_rows = $task_field_value_repository
				->execute_raw( $ranked_cte, array( $status_field_id, $position_field_id, $status_id ) );

			// Return true even if 0 rows were affected (positions were already correct)
			return false !== $affected_rows;
		} );
	}
}
