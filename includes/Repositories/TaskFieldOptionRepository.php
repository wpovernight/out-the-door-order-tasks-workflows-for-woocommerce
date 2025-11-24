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
}
