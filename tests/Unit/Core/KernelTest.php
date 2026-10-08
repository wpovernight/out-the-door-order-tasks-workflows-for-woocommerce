<?php

namespace WPO\OTD\Tests\Unit\Core;

use WPO\OTD\Core\Container\Container;
use WPO\OTD\Core\Container\DeferrableProvider;
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

	public function test_deferred_providers_load_when_one_of_their_ids_is_first_resolved(): void {
		$this->kernel->add_provider( $this->deferred_provider( 'lazy', array( 'first', 'second' ) ) );
		$this->kernel->boot();

		$this->assertSame( array(), $this->calls, 'A deferred provider must not load at boot.' );
		$this->assertTrue( $this->container->has( 'first' ) );

		$this->assertSame( 'first', $this->container->get( 'first' )->id );
		$this->assertSame( 'second', $this->container->get( 'second' )->id );

		$this->assertSame( array( 'lazy:register', 'lazy:boot' ), $this->calls );
	}

	public function test_deferred_providers_are_not_booted_when_only_registering(): void {
		$this->kernel->add_provider( $this->deferred_provider( 'lazy', array( 'first' ) ) );
		$this->kernel->register();

		$this->container->get( 'first' );

		$this->assertSame( array( 'lazy:register' ), $this->calls );
	}

	public function test_a_deferred_provider_resolved_during_the_boot_phase_is_booted(): void {
		$this->kernel->add_provider( $this->deferred_provider( 'lazy', array( 'first' ) ) );
		$this->kernel->add_provider(
			$this->provider(
				'eager',
				static function ( Container $container ) {
					$container->get( 'first' );
				}
			)
		);

		$this->kernel->boot();

		$this->assertSame( array( 'eager:register', 'eager:boot', 'lazy:register', 'lazy:boot' ), $this->calls );
	}

	/**
	 * A provider that records its calls.
	 *
	 * @param string        $name
	 * @param callable|null $on_boot Extra work to do while booting.
	 *
	 * @return ServiceProvider
	 */
	private function provider( string $name, ?callable $on_boot = null ): ServiceProvider {
		$calls = &$this->calls;

		return new class( $name, $calls, $on_boot ) implements ServiceProvider {
			private array $calls;

			public function __construct( private string $name, array &$calls, private $on_boot ) {
				$this->calls = &$calls;
			}

			public function register( Container $container ): void {
				$this->calls[] = $this->name . ':register';
			}

			public function boot( Container $container ): void {
				$this->calls[] = $this->name . ':boot';

				if ( $this->on_boot ) {
					( $this->on_boot )( $container );
				}
			}
		};
	}

	/**
	 * A deferred provider that records its calls and binds each promised id to an object naming it.
	 *
	 * @param string   $name
	 * @param string[] $ids
	 *
	 * @return DeferrableProvider
	 */
	private function deferred_provider( string $name, array $ids ): DeferrableProvider {
		$calls = &$this->calls;

		return new class( $name, $ids, $calls ) implements DeferrableProvider {
			private array $calls;

			public function __construct( private string $name, private array $ids, array &$calls ) {
				$this->calls = &$calls;
			}

			public function provides(): array {
				return $this->ids;
			}

			public function register( Container $container ): void {
				$this->calls[] = $this->name . ':register';

				foreach ( $this->ids as $id ) {
					$container->bind( $id, static fn() => (object) array( 'id' => $id ) );
				}
			}

			public function boot( Container $container ): void {
				$this->calls[] = $this->name . ':boot';
			}
		};
	}
}
