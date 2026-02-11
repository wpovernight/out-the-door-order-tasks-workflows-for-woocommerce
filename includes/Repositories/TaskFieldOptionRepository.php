<?php

namespace WPO\AOM\Repositories;

use WPO\AOM\Models\TaskFieldOption;

defined( 'ABSPATH' ) || exit;

class TaskFieldOptionRepository extends BaseRepository {
	private static string $table_name = 'task_field_options';
	private static string $model_class = TaskFieldOption::class;
	protected bool $enable_cache = true;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( self::$table_name, self::$model_class );
	}

	/**
	 * Get field options by field ID, ordered by position.
	 *
	 * @param int $field_id The field ID.
	 * @return TaskFieldOption[]
	 */
	public function get_by_field_id_ordered( int $field_id ): array {
		return $this->where( 'field_id', $field_id )
		            ->order_by( 'position' )
		            ->get();
	}

	/**
	 * Update positions of field options based on the provided ordered list of option IDs.
	 *
	 * @param int   $field_id           The field ID.
	 * @param array $ordered_option_ids An array of option IDs in the desired order.
	 */
	public function update_positions( int $field_id, array $ordered_option_ids ): void {
		if ( empty( $ordered_option_ids ) ) {
			return;
		}

		// Build CASE statement for position updates.
		$case_parts = array();
		$bindings   = array();

		foreach ( $ordered_option_ids as $position => $option_id ) {
			$case_parts[] = 'WHEN id = %d THEN %d';
			$bindings[]   = absint( $option_id );
			$bindings[]   = absint( $position + 1 ); // Start positions from 1 instead of 0.
		}

		$case_statement = implode( ' ', $case_parts );

		// Add field_id to bindings for WHERE clause.
		$bindings[] = absint( $field_id );

		// Add option IDs for IN clause.
		foreach ( $ordered_option_ids as $option_id ) {
			$bindings[] = absint( $option_id );
		}

		// Build placeholders for IN clause.
		$placeholders = implode( ',', array_fill( 0, count( $ordered_option_ids ), '%d' ) );

		$query = "UPDATE {$this->get_table_full_name()}
				  SET position = CASE {$case_statement} END
				  WHERE field_id = %d
				  AND id IN ({$placeholders})";

		$this->execute_raw( $query, $bindings );
	}
}
