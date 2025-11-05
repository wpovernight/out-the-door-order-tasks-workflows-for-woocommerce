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
}
