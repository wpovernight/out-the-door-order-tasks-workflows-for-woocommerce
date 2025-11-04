<?php

namespace WPO\AOM\Repositories;

use WPO\AOM\Models\Task;

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
	 *
	 * @return float|null
	 */
	public function get_next_task_position( ?int $given_task_id, ?int $status_id = null ): ?float {
		// Validate given task ID.
		if ( ! empty( $given_task_id ) ) {
			$given_task = $this->find( $given_task_id );
			if ( ! $given_task ) {
				return null;
			}
		}

		// If no task ID is given, the first position is returned.
		if ( empty( $given_task_id ) && empty( $status_id ) ) {
			throw new \InvalidArgumentException( 'Either given_task_id or status_id must be provided.' );
		}

		$task_field_value_repository = new TaskFieldValueRepository();
		$task_field_repository = new TaskFieldRepository();

		$status_field = $task_field_repository->find_by_slug( 'status' );
		$position_field = $task_field_repository->find_by_slug( 'position' );

		$status_field_id   = (int) $status_field->id;
		$position_field_id = (int) $position_field->id;
		$task_field_value_table_name = $task_field_value_repository->get_table_full_name();
		$given_task_status = empty( $given_task_id ) ? $status_id : $task_field_value_repository->find_by_task_and_field( $given_task->id, $status_field->id )->value;
		$given_task_position = empty( $given_task_id ) ? 0.0 : $task_field_value_repository->find_by_task_and_field( $given_task->id, $position_field->id )->value;

		if ( is_null( $given_task_status ) ) {
			return null;
		}

		$next_task_value_field = $task_field_value_repository
			->select(array('position.*'))
			->alias('position')
			->join("{$task_field_value_table_name} AS status", 'position.task_id', '=', 'status.task_id')
			->where('status.field_id', $status_field_id)
			->where('status.value', $given_task_status)
			->where('position.field_id', $position_field_id)
			->where_raw("CAST(position.value AS DECIMAL(10,5)) > {$given_task_position}")
			->order_by_raw('CAST(position.value AS DECIMAL(10,5))')
			->first();

		return $next_task_value_field ? (float) $next_task_value_field->value : null;
	}
}
