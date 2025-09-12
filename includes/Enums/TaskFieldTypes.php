<?php

namespace WPO\AOM\Enums;

defined( 'ABSPATH' ) || exit;

final class TaskFieldTypes {

	public const TEXT = 'text';
	public const NUMBER = 'number';
	public const SELECT = 'select';
	public const DATE = 'date';

	/**
	 * Get all valid field types.
	 *
	 * @return array
	 */
	public static function all(): array {
		return array(
			self::TEXT,
			self::NUMBER,
			self::SELECT,
			self::DATE,
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
