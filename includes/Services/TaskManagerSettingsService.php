<?php

namespace WPO\OTD\Services;

defined( 'ABSPATH' ) || exit;

class TaskManagerSettingsService extends BaseSettingsService {
	public const OPTION_NAME = 'wpo_otd_task_manager_settings';
	protected array $defaults = array(
		'view' => 'kanban',
	);
}
