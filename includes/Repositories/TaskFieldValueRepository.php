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
	 * Update multiple field values for a task.
	 * Deletes existing values and inserts new ones in a transaction.
	 *
	 * @param int $task_id
	 * @param array<int, mixed> $field_values Field ID => value (string or array of strings)
	 *
	 * @return int|false Number of rows inserted or false on failure.
	 * @throws \Exception
	 */
	public function update_task_multiple_field_values( int $task_id, array $field_values ) {
		if ( empty( $field_values ) ) {
			return 0;
		}

		$field_ids = array_map( 'intval', array_keys( $field_values ) );

		// Build rows for insertion
		$rows     = array();
		$bindings = array();

		foreach ( $field_values as $field_id => $value ) {
			$field_id = (int) $field_id;

			foreach ( (array) $value as $single ) {
				$rows[]     = '(%d, %d, %s)';
				$bindings[] = $task_id;
				$bindings[] = $field_id;
				$bindings[] = $single;
			}
		}

		return $this->transaction( function () use ( $task_id, $field_ids, $rows, $bindings ) {
			$this->where( 'task_id', $task_id )
			     ->where( 'field_id', 'IN', $field_ids )
			     ->delete_raw();

			return $this->insert_raw(
				'`task_id`, `field_id`, `value`',
				$rows,
				$bindings
			);
		} );
	}
}
