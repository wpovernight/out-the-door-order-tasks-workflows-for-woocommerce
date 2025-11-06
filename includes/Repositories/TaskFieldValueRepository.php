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

	/**
	 * Update multiple field values for a given task.
	 *
	 * @param int $task_id
	 * @param array $field_values Associative array of field_id => value
	 *
	 * @return bool|int
	 */
	public function update_task_multiple_field_values( int $task_id, array $field_values ): int {
		if ( empty( $field_values ) ) {
			return false;
		}

		$cases = array();
		foreach ( $field_values as $field_id => $value ) {
			$escaped_value = esc_sql( $value );
			$field_id      = (int) $field_id;
			$cases[]       = "WHEN {$field_id} THEN '{$escaped_value}'";
		}
		$case_sql   = 'CASE `field_id` ' . implode( ' ', $cases ) . ' END';
		$set_clause = "`value` = {$case_sql}";

		return $this->where( 'task_id', $task_id )
		            ->where( 'field_id', 'IN', array_keys( $field_values ) )
		            ->update_raw( $set_clause );
	}
}
