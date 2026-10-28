<?php

namespace WPO\OTD\Exceptions;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * A custom order status cannot change that way while it is being deleted.
 *
 * The message says so in the site's language, so it can be shown to the user.
 */
final class StatusBeingDeletedException extends RuntimeException {
}
