<?php

namespace WPO\AOM\Repositories;

use WPO\AOM\Models\TaskField;

defined( 'ABSPATH' ) || exit;

class TaskFieldRepository extends BaseRepository {
	private static string $table_name = 'task_fields';
	private static string $model_class = TaskField::class;
	protected bool $enable_cache = true;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( self::$table_name, self::$model_class );
	}
}
