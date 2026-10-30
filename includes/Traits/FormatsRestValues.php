<?php

namespace WPO\OTD\Traits;

use DateTimeImmutable;
use DateTimeZone;
use stdClass;

defined( 'ABSPATH' ) || exit;

/**
 * Puts values in the form a REST answer sends them: a map that may be empty, and a stored UTC time.
 */
trait FormatsRestValues {
	/**
	 * A map as it is sent: an empty one as an object, since PHP's empty array would be sent as [].
	 *
	 * @param array $map
	 *
	 * @return array|stdClass
	 */
	protected static function map( array $map ): array|stdClass {
		return array() === $map ? new stdClass() : $map;
	}

	/**
	 * A stored UTC time as it is sent: ISO 8601 with "Z".
	 *
	 * @param string|null $datetime "Y-m-d H:i:s", in UTC.
	 *
	 * @return string|null Null for none, for one that is not in that format, and for a date or time that cannot be,
	 *                     such as 0000-00-00 00:00:00 or 2026-02-30 25:61:61, which PHP would roll over to another.
	 */
	protected static function utc( ?string $datetime ): ?string {
		if ( null === $datetime ) {
			return null;
		}

		$time = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $datetime, new DateTimeZone( 'UTC' ) );

		// Read back in the same format, a time PHP rolled over no longer matches.
		return false === $time || $time->format( 'Y-m-d H:i:s' ) !== $datetime ? null : $time->format( 'Y-m-d\TH:i:s\Z' );
	}
}
