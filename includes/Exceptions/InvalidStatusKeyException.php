<?php

namespace WPO\OTD\Exceptions;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * A custom order status cannot take a status key: it does not fit, or another order status uses it.
 *
 * The message says why in the site's language, so it can be shown to the user.
 */
final class InvalidStatusKeyException extends InvalidArgumentException {
}
