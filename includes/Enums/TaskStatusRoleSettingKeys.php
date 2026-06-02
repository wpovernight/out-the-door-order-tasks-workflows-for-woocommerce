<?php

namespace WPO\AOM\Enums;

defined( 'ABSPATH' ) || exit;

final class TaskStatusRoleSettingKeys {
	public const DONE   = 'role_done_option_id';
	public const UNDONE = 'role_undone_option_id';

	/**
	 * Get all valid field types.
	 *
	 * @return array
	 */
	public static function all(): array {
		return array(
			self::DONE,
			self::UNDONE,
		);
	}

	/**
	 * Check if a field type is valid.
	 *
	 * @param string $type
	 *
	 * @return bool
	 */
	public static function is_valid( string $type ): bool {
		return in_array( $type, self::all(), true );
	}
}
