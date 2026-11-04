<?php

namespace WPO\OTD\Repositories;

use WPO\OTD\Models\TaskFieldOption;

defined( 'ABSPATH' ) || exit;

class TaskFieldOptionRepository extends BaseRepository {
	protected bool $enable_cache = true;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'task_field_options', TaskFieldOption::class );
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

		$case_parts = array();
		$bindings   = array();

		foreach ( $ordered_option_ids as $position => $option_id ) {
			$case_parts[] = 'WHEN id = %d THEN %d';
			$bindings[]   = (int) $option_id;
			$bindings[]   = absint( $position + 1 );
		}

		$case_statement = implode( ' ', $case_parts );

		$bindings[] = $field_id;

		foreach ( $ordered_option_ids as $option_id ) {
			$bindings[] = (int) $option_id;
		}

		$placeholders = self::in_placeholders( $ordered_option_ids, '%d' );

		$query = "UPDATE {$this->get_table_full_name()}
				  SET position = CASE {$case_statement} END
				  WHERE field_id = %d
				  AND id IN ({$placeholders})";

		$this->execute_raw( $query, $bindings );
	}

	/**
	 * Increment positions of field options starting from a specific position for a given field ID.
	 *
	 * @param int $field_id
	 * @param int $start_position
	 *
	 * @return void
	 */
	public function increment_positions_from( int $field_id, int $start_position ): void {
		$query = "UPDATE {$this->get_table_full_name()}
				  SET position = position + 1
				  WHERE field_id = %d
				  AND position >= %d";

		$this->execute_raw( $query, array( $field_id, absint( $start_position ) ) );
	}
}
