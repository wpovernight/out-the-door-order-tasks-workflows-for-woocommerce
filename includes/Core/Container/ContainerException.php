<?php

namespace WPO\OTD\Core\Container;

use RuntimeException;
use Psr\Container\ContainerExceptionInterface;

defined( 'ABSPATH' ) || exit;

final class ContainerException extends RuntimeException implements ContainerExceptionInterface {
}
