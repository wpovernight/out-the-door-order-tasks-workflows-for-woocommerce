<?php

namespace WPO\AOM\Repositories;

use InvalidArgumentException;
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
	 * @param int|null $given_task_id
	 * @param int|null $status_id
	 * @param int $status_field_id
	 * @param int $position_field_id
	 * @param float|null $given_task_position
	 *
	 * @return float|null
	 * @throws InvalidArgumentException
	 */
	public function get_next_task_position(
		?int $given_task_id,
		int $status_id,
		int $status_field_id,
		int $position_field_id,
		?float $given_task_position = null
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

		$next_task_value_field = $task_field_value_repository
			->select(array('position.*'))
			->alias('position')
			->join("{$task_field_value_table_name} AS status", 'position.task_id', '=', 'status.task_id')
			->where('status.field_id', $status_field_id)
			->where('status.value', $status_id)
			->where('position.field_id', $position_field_id)
			->where_raw("CAST(position.value AS DECIMAL(10,5)) > {$given_task_position}")
			->order_by_raw('CAST(position.value AS DECIMAL(10,5))')
			->first();

		return $next_task_value_field ? (float) $next_task_value_field->value : null;
	}
}
