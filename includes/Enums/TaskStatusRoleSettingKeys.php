<?php

namespace WPO\OTD\Enums;

defined( 'ABSPATH' ) || exit;

final class TaskStatusRoleSettingKeys {
	public const DONE   = 'role_done_option_id';
	public const UNDONE = 'role_undone_option_id';

	/**
	 * Get every status role setting key.
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
	 * Check whether a key is a status role setting key.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public static function is_valid( string $key ): bool {
		return in_array( $key, self::all(), true );
	}
}
