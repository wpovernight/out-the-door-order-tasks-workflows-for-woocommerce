<?php

namespace WPO\AOM\Repositories;

use WPO\AOM\Models\TaskFieldValue;

defined( 'ABSPATH' ) || exit;

class TaskFieldValueRepository extends BaseRepository {
	private static string $table_name = 'task_field_values';
	private static string $model_class = TaskFieldValue::class;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( self::$table_name, self::$model_class );
	}

	/**
	 * Find a TaskFieldValue by field ID and task ID.
	 *
	 * @param int $task_id
	 * @param int $field_id
	 *
	 * @return TaskFieldValue|null
	 */
	public function find_by_task_and_field( int $task_id, int $field_id ): ?TaskFieldValue {
		return $this
			->where( 'task_id', $task_id )
			->where( 'field_id', $field_id )
			->first();
	}
}
