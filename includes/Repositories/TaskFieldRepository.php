<?php

namespace WPO\AOM\Repositories;

use WPO\AOM\Models\TaskField;

defined( 'ABSPATH' ) || exit;

class TaskFieldRepository extends BaseRepository {
	private static string $table_name = 'task_fields';
	private static string $model_class = TaskField::class;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( self::$table_name, self::$model_class );
	}

	/**
	 * Find a TaskField by its label.
	 *
	 * @param string $label
	 *
	 * @return TaskField|null
	 */
	public function find_by_slug( string $label ): ?TaskField {
		return $this->where( 'slug', $label )->first();
	}
}
