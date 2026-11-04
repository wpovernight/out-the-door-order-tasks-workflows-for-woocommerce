<?php

namespace WPO\OTD\Tests\Unit\Core;

use WPO\OTD\Core\Container\Container;
use WPO\OTD\Core\Container\ServiceProvider;
use WPO\OTD\Core\Kernel;
use WPO\OTD\Tests\TestCase;

class KernelTest extends TestCase {
	/**
	 * Every provider call, in order, as "<provider>:<phase>".
	 *
	 * @var string[]
	 */
	private array $calls = array();

	private Container $container;
	private Kernel $kernel;

	protected function setUp(): void {
		parent::setUp();

		$this->calls     = array();
		$this->container = new Container();
		$this->kernel    = new Kernel( $this->container );
	}

	public function test_exposes_its_container(): void {
		$this->assertSame( $this->container, $this->kernel->container() );
	}

	public function test_register_registers_eager_providers_without_booting_them(): void {
		$this->kernel->add_provider( $this->provider( 'a' ) );
		$this->kernel->add_provider( $this->provider( 'b' ) );

		$this->kernel->register();

		$this->assertSame( array( 'a:register', 'b:register' ), $this->calls );
	}

	public function test_boot_registers_every_provider_before_booting_any(): void {
		$this->kernel->add_provider( $this->provider( 'a' ) );
		$this->kernel->add_provider( $this->provider( 'b' ) );

		$this->kernel->boot();

		$this->assertSame( array( 'a:register', 'b:register', 'a:boot', 'b:boot' ), $this->calls );
	}

	public function test_register_and_boot_run_once(): void {
		$this->kernel->add_provider( $this->provider( 'a' ) );

		$this->kernel->register();
		$this->kernel->register();
		$this->kernel->boot();
		$this->kernel->boot();

		$this->assertSame( array( 'a:register', 'a:boot' ), $this->calls );
	}

	/**
	 * A provider that records its calls.
	 *
	 * @param string $name
	 *
	 * @return ServiceProvider
	 */
	private function provider( string $name ): ServiceProvider {
		$calls = &$this->calls;

		return new class( $name, $calls ) implements ServiceProvider {
			private array $calls;

			public function __construct( private string $name, array &$calls ) {
				$this->calls = &$calls;
			}

			public function register( Container $container ): void {
				$this->calls[] = $this->name . ':register';
			}

			public function boot( Container $container ): void {
				$this->calls[] = $this->name . ':boot';
			}
		};
	}
}
