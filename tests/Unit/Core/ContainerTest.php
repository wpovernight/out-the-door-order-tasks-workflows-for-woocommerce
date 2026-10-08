<?php

namespace WPO\OTD\Tests\Unit\Core;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use WPO\OTD\Core\Container\Container;
use WPO\OTD\Core\Container\ContainerException;
use WPO\OTD\Core\Container\NotFoundException;
use WPO\OTD\Tests\TestCase;
use WPO\OTD\Tests\Unit\Core\Fixtures\Car;
use WPO\OTD\Tests\Unit\Core\Fixtures\CycleA;
use WPO\OTD\Tests\Unit\Core\Fixtures\EnglishGreeter;
use WPO\OTD\Tests\Unit\Core\Fixtures\Engine;
use WPO\OTD\Tests\Unit\Core\Fixtures\Greeter;
use WPO\OTD\Tests\Unit\Core\Fixtures\NeedsScalar;
use WPO\OTD\Tests\Unit\Core\Fixtures\WithOptionalArguments;

require_once __DIR__ . '/Fixtures/ContainerFixtures.php';

class ContainerTest extends TestCase {
	private Container $container;

	protected function setUp(): void {
		parent::setUp();

		$this->container = new Container();
	}

	public function test_it_is_a_psr_container(): void {
		$this->assertInstanceOf( ContainerInterface::class, $this->container );
	}

	public function test_autowires_a_class_without_a_constructor(): void {
		$this->assertInstanceOf( Engine::class, $this->container->get( Engine::class ) );
	}

	public function test_autowires_constructor_dependencies_recursively(): void {
		$car = $this->container->get( Car::class );

		$this->assertInstanceOf( Engine::class, $car->engine );
		$this->assertSame( $this->container->get( Engine::class ), $car->engine );
	}

	public function test_autowired_services_are_shared(): void {
		$this->assertSame( $this->container->get( Car::class ), $this->container->get( Car::class ) );
	}

	public function test_autowiring_uses_defaults_and_null_for_scalars(): void {
		$service = $this->container->get( WithOptionalArguments::class );

		$this->assertInstanceOf( Engine::class, $service->engine );
		$this->assertNull( $service->name );
		$this->assertSame( 4, $service->wheels );
	}

	public function test_autowiring_a_required_scalar_throws(): void {
		$this->expectException( ContainerException::class );
		// Exception messages go through esc_html().
		$this->expectExceptionMessage( 'Cannot autowire parameter &quot;$dsn&quot; of ' . NeedsScalar::class . '.' );

		$this->container->get( NeedsScalar::class );
	}

	public function test_resolving_an_unknown_id_throws_not_found(): void {
		try {
			$this->container->get( 'Not\\A\\Class' );
			$this->fail( 'Expected a NotFoundException.' );
		} catch ( NotFoundException $e ) {
			$this->assertInstanceOf( NotFoundExceptionInterface::class, $e );
			$this->assertStringContainsString( 'Not\\A\\Class', $e->getMessage() );
		}
	}

	public function test_binding_an_unknown_id_without_a_factory_fails_on_resolve_not_on_bind(): void {
		$this->container->bind( 'Not\\A\\Class' );

		$this->assertTrue( $this->container->has( 'Not\\A\\Class' ) );

		$this->expectException( NotFoundException::class );
		$this->container->get( 'Not\\A\\Class' );
	}

	public function test_circular_dependencies_are_reported_with_their_chain(): void {
		try {
			$this->container->get( CycleA::class );
			$this->fail( 'Expected a ContainerException.' );
		} catch ( ContainerException $e ) {
			$this->assertInstanceOf( ContainerExceptionInterface::class, $e );
			$this->assertStringContainsString( 'Circular dependency detected while resolving', $e->getMessage() );
			$this->assertStringContainsString( 'CycleA -&gt; ', $e->getMessage() );
		}

		// The failed resolution must not leave ids flagged as mid-construction.
		$this->container->bind( CycleA::class, static fn() => new \stdClass() );
		$this->assertInstanceOf( \stdClass::class, $this->container->get( CycleA::class ) );
	}

	public function test_factories_receive_the_container(): void {
		$received = null;

		$this->container->bind(
			'service',
			static function ( Container $container ) use ( &$received ) {
				$received = $container;

				return new \stdClass();
			}
		);

		$this->container->get( 'service' );

		$this->assertSame( $this->container, $received );
	}

	public function test_shared_bindings_resolve_once(): void {
		$calls = 0;

		$this->container->singleton(
			'service',
			static function () use ( &$calls ) {
				++$calls;

				return new \stdClass();
			}
		);

		$this->assertSame( $this->container->get( 'service' ), $this->container->get( 'service' ) );
		$this->assertSame( 1, $calls );
	}

	public function test_non_shared_bindings_resolve_a_new_instance_every_time(): void {
		$this->container->bind( 'service', static fn() => new \stdClass(), false );

		$this->assertNotSame( $this->container->get( 'service' ), $this->container->get( 'service' ) );
	}

	public function test_rebinding_replaces_the_cached_instance(): void {
		$this->container->singleton( 'service', static fn() => (object) array( 'version' => 1 ) );
		$this->assertSame( 1, $this->container->get( 'service' )->version );

		$this->container->singleton( 'service', static fn() => (object) array( 'version' => 2 ) );
		$this->assertSame( 2, $this->container->get( 'service' )->version );
	}

	public function test_aliases_resolve_to_the_shared_concrete_instance(): void {
		$this->container->alias( Greeter::class, EnglishGreeter::class );

		$greeter = $this->container->get( Greeter::class );

		$this->assertInstanceOf( EnglishGreeter::class, $greeter );
		$this->assertSame( $this->container->get( EnglishGreeter::class ), $greeter );
	}

	public function test_has_reports_bindings_instances_and_deferred_ids(): void {
		$this->assertFalse( $this->container->has( Engine::class ) );

		$this->container->get( Engine::class );
		$this->assertTrue( $this->container->has( Engine::class ), 'A resolved instance counts.' );

		$this->container->bind( 'bound', static fn() => new \stdClass() );
		$this->assertTrue( $this->container->has( 'bound' ) );

		$this->container->defer( 'deferred', static function () {} );
		$this->assertTrue( $this->container->has( 'deferred' ) );

		$this->container->forget_deferred( array( 'deferred' ) );
		$this->assertFalse( $this->container->has( 'deferred' ) );
	}

	public function test_resolving_a_deferred_id_runs_its_loader_first(): void {
		$loads = 0;

		$this->container->defer(
			'lazy',
			function ( Container $container ) use ( &$loads ) {
				++$loads;
				$container->forget_deferred( array( 'lazy' ) );
				$container->bind( 'lazy', static fn() => (object) array( 'loaded' => true ) );
			}
		);

		$this->assertTrue( $this->container->get( 'lazy' )->loaded );
		$this->container->get( 'lazy' );
		$this->assertSame( 1, $loads );
	}

	public function test_a_deferred_loader_that_resolves_its_own_id_does_not_build_a_duplicate(): void {
		$built    = 0;
		$resolved = null;

		$this->container->defer(
			'lazy',
			function ( Container $container ) use ( &$built, &$resolved ) {
				$container->forget_deferred( array( 'lazy' ) );
				$container->bind(
					'lazy',
					static function () use ( &$built ) {
						++$built;

						return new \stdClass();
					}
				);

				// A provider's boot() may resolve its own service while loading.
				$resolved = $container->get( 'lazy' );
			}
		);

		$instance = $this->container->get( 'lazy' );

		$this->assertSame( $resolved, $instance );
		$this->assertSame( 1, $built );
	}
}
