<?php

namespace WPO\AOM\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * Default task field IDs.
 *
 * These IDs match the hardcoded values in Install.php.
 * Protected fields cannot be deleted by users.
 */
final class DefaultTaskFields {
	// Protected system fields
	public const STATUS         = 1;
	public const POSITION       = 2;
	public const CREATOR        = 3;
	public const ORDER          = 4;
	public const DUE_DATE       = 5;
	public const COMPLETED_DATE = 6;
	public const ARCHIVED_DATE  = 7;

	// User-editable default fields
	public const PRIORITY = 8;

	/**
	 * Get all default field IDs.
	 *
	 * @return array
	 */
	public static function all(): array {
		return array(
			self::STATUS,
			self::POSITION,
			self::CREATOR,
			self::ORDER,
			self::DUE_DATE,
			self::COMPLETED_DATE,
			self::ARCHIVED_DATE,
			self::PRIORITY,
		);
	}

	/**
	 * Get protected field IDs that cannot be deleted.
	 *
	 * @return array
	 */
	public static function protected(): array {
		return array(
			self::STATUS,
			self::POSITION,
			self::CREATOR,
			self::ORDER,
			self::DUE_DATE,
			self::COMPLETED_DATE,
			self::ARCHIVED_DATE,
		);
	}

	/**
	 * Get editable field IDs.
	 *
	 * @return array
	 */
	public static function editable(): array {
		return array(
			self::STATUS,
			self::PRIORITY,
		);
	}

	/**
	 * Get required field IDs.
	 *
	 * @return array
	 */
	public static function required(): array {
		return array(
			self::STATUS,
			self::POSITION,
		);
	}

	/**
	 * Check if a field ID is a default field.
	 *
	 * @param int $field_id
	 *
	 * @return bool
	 */
	public static function is_default( int $field_id ): bool {
		return in_array( $field_id, self::all(), true );
	}

	/**
	 * Check if a field ID is protected.
	 *
	 * @param int $field_id
	 *
	 * @return bool
	 */
	public static function is_protected( int $field_id ): bool {
		return in_array( $field_id, self::protected(), true );
	}

	/**
	 * Check if a field ID is editable.
	 *
	 * @param int $field_id
	 *
	 * @return bool
	 */
	public static function is_editable( int $field_id ): bool {
		return in_array( $field_id, self::editable(), true );
	}

	/**
	 * Check if a field ID is required.
	 *
	 * @param int $field_id
	 *
	 * @return bool
	 */
	public static function is_required( int $field_id ): bool {
		return in_array( $field_id, self::required(), true );
	}
}
