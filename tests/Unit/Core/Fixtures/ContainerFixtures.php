<?php
/**
 * Small classes for exercising the container's autowiring.
 *
 * PSR-4 cannot autoload a file holding several classes, so the tests that use them require it.
 */

namespace WPO\OTD\Tests\Unit\Core\Fixtures;

class Engine {
}

class Car {
	public function __construct( public Engine $engine ) {
	}
}

class WithOptionalArguments {
	public function __construct( public Engine $engine, public ?string $name, public int $wheels = 4 ) {
	}
}

class NeedsScalar {
	public function __construct( public string $dsn ) {
	}
}

class CycleA {
	public function __construct( public CycleB $b ) {
	}
}

class CycleB {
	public function __construct( public CycleA $a ) {
	}
}

interface Greeter {
}

class EnglishGreeter implements Greeter {
}
