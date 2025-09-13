<?php

namespace WPO\AOM\Repositories;

use WPO\AOM\Models\Task;

defined( 'ABSPATH' ) || exit;

class TaskRepository extends BaseRepository {
	private static string $table_name = 'tasks';
	private static string $model_class = Task::class;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( self::$table_name, self::$model_class );
	}
}
