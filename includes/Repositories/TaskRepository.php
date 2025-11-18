<?php

namespace WPO\AOM\Repositories;

use InvalidArgumentException;
use WPO\AOM\Models\Task;
use WPO\AOM\Models\TaskField;
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
	 * @param int|null $given_task_id
	 * @param int|null $status_id
	 * @param int $status_field_id
	 * @param int $position_field_id
	 * @param float|null $given_task_position
	 * @param int|null $moving_task_id
	 *
	 * @return float|null
	 * @throws InvalidArgumentException
	 */
	public function get_next_task_position(
		?int $given_task_id,
		int $status_id,
		int $status_field_id,
		int $position_field_id,
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
				->find_by_task_and_field( $given_task_id, $position_field_id );
			$given_task_position    = $given_task_field_value ? (float) $given_task_field_value->value : 0.0;
		}

		$query = $task_field_value_repository
			->select(array('position.*'))
			->alias('position')
			->join("{$task_field_value_table_name} AS status", 'position.task_id', '=', 'status.task_id')
			->where('status.field_id', $status_field_id)
			->where('status.value', $status_id)
			->where('position.field_id', $position_field_id)
			->where('position.task_id', '!=', $given_task_id ?? 0)
			->where_raw("CAST(position.value AS DECIMAL(10,5)) > {$given_task_position}");

		if ( $moving_task_id ) {
			$query->where( 'position.task_id', '!=', $moving_task_id );
		}

		$next_task_value_field = $query
			->order_by_raw('CAST(position.value AS DECIMAL(10,5)) ASC')
			->first();

		return $next_task_value_field ? (float) $next_task_value_field->value : null;
	}

	/**
	 * Get the position of the last task in a given status.
	 *
	 * @param int $status_id
	 * @param int $status_field_id
	 * @param int $position_field_id
	 *
	 * @return float|null
	 */
	public function get_last_task_position(
		int $status_id,
		int $status_field_id,
		int $position_field_id
	): ?float {
		$task_field_value_repository = RepositoryRegistry::get( TaskFieldValue::class );
		$task_field_value_table_name = $task_field_value_repository->get_table_full_name();

		$last_task_value_field = $task_field_value_repository
			->select(array('position.*'))
			->alias('position')
			->join("{$task_field_value_table_name} AS status", 'position.task_id', '=', 'status.task_id')
			->where('status.field_id', $status_field_id)
			->where('status.value', $status_id)
			->where('position.field_id', $position_field_id)
			->order_by_raw('CAST(position.value AS DECIMAL(10,5)) DESC')
			->first();

		return $last_task_value_field ? (float) $last_task_value_field->value : null;
	}

	/**
	 * Rebalance positions of tasks, optionally within a specific status.
	 *
	 * @param int|null $status_id
	 *
	 * @return void
	 */
	public function rebalance_positions( ?int $status_id = null ): void {
		$task_field_value_repository = RepositoryRegistry::get( TaskFieldValue::class );

		$task_field_repository = RepositoryRegistry::get( TaskField::class );
		$fields                = $task_field_repository->get();
		$fields_by_slug        = array_column( $fields, null, 'slug' );
		$status_field_id       = $fields_by_slug['status']->id ?? null;
		$position_field_id     = $fields_by_slug['position']->id ?? null;

		if ( ! $status_field_id || ! $position_field_id ) {
			return;
		}

		$task_field_value_table_name = $task_field_value_repository->get_table_full_name();
		$ranked_cte = "
			WITH ranked AS (
				SELECT
					position.id,
					ROW_NUMBER() OVER (
						PARTITION BY status.value
						ORDER BY
							CAST(position.value AS DECIMAL(10, 5))
					) AS new_position
				FROM
					{$task_field_value_table_name} AS position
					INNER JOIN {$task_field_value_table_name} AS status ON position.task_id = status.task_id
				WHERE
					status.field_id = '{$status_field_id}'
					AND position.field_id = '{$position_field_id}'
		";

		if ( $status_id ) {
			$ranked_cte .= " AND status.value = '{$status_id}' ";
		}

		$ranked_cte .= "
			)
			UPDATE
				{$task_field_value_table_name} AS p
				INNER JOIN ranked r ON p.id = r.id
			SET
				p.value = r.new_position;
		";

		$task_field_value_repository->execute_raw( $ranked_cte );
	}
}
