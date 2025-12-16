<?php

namespace WPO\AOM\Enums;

defined( 'ABSPATH' ) || exit;

final class FulfillmentStatuses {
	public const NOT_FULFILLED       = 'not-fulfilled';
	public const FULFILLED           = 'fulfilled';
	public const PARTIALLY_FULFILLED = 'partially-fulfilled';

	/**
	 * Get all valid fulfilled statuses.
	 *
	 * @return array
	 */
	public static function all(): array {
		return array(
			self::NOT_FULFILLED,
			self::FULFILLED,
			self::PARTIALLY_FULFILLED
		);
	}

	public static function is_valid( string $type ): bool {
		return in_array( $type, self::all(), true );
	}
}
