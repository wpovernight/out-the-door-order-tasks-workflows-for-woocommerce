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
	 * @return string|null Null for none, or for one that is not in that format.
	 */
	protected static function utc( ?string $datetime ): ?string {
		if ( null === $datetime ) {
			return null;
		}

		$time = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $datetime, new DateTimeZone( 'UTC' ) );

		return false === $time ? null : $time->format( 'Y-m-d\TH:i:s\Z' );
	}
}
