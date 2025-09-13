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
}
