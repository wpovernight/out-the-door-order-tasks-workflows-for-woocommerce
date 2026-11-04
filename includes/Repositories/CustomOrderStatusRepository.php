<?php

namespace WPO\OTD\Repositories;

use WPO\OTD\Models\CustomOrderStatus;

defined( 'ABSPATH' ) || exit;

class CustomOrderStatusRepository extends BaseRepository {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'custom_statuses', CustomOrderStatus::class );
	}
}
