<?php

namespace WPO\OTD\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * The kinds of admin notice WordPress styles, each backed by its CSS class suffix.
 */
enum NoticeType: string {
	case ERROR   = 'error';
	case WARNING = 'warning';
	case SUCCESS = 'success';
	case INFO    = 'info';
}
