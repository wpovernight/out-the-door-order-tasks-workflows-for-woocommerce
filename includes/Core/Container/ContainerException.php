<?php

namespace WPO\AOM\Core\Container;

use RuntimeException;
use Psr\Container\ContainerExceptionInterface;

defined( 'ABSPATH' ) || exit;

final class ContainerException extends RuntimeException implements ContainerExceptionInterface {
}
