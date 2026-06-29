<?php

namespace WPO\AOM\Core\Container;

use InvalidArgumentException;
use Psr\Container\NotFoundExceptionInterface;

defined( 'ABSPATH' ) || exit;

final class NotFoundException extends InvalidArgumentException implements NotFoundExceptionInterface {
}
