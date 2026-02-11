<?php

namespace WPO\AOM\Repositories;

use WPO\AOM\Models\CustomOrderStatus;

defined( 'ABSPATH' ) || exit;

class CustomOrderStatusRepository extends BaseRepository {
	private static string $table_name = 'custom_statuses';
	private static string $model_class = CustomOrderStatus::class;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( self::$table_name, self::$model_class );
	}
}
