<?php

namespace WPO\OTD\Exceptions;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Another custom order status already uses the status key a change asked for.
 *
 * The message says so in the site's language, so it can be shown to the user.
 */
final class StatusKeyTakenException extends RuntimeException {
}
