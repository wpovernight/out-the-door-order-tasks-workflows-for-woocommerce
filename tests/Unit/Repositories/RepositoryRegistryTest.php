<?php

namespace WPO\OTD\Tests\Unit\Repositories;

use Mockery;
use WPO\OTD\Repositories\BaseRepository;
use WPO\OTD\Repositories\RepositoryRegistry;
use WPO\OTD\Tests\TestCase;

class RepositoryRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->set_static_property( RepositoryRegistry::class, 'map', array() );
	}

	protected function tearDown(): void {
		$this->set_static_property( RepositoryRegistry::class, 'map', array() );

		parent::tearDown();
	}

	public function test_the_factory_runs_lazily_and_once(): void {
		$repository = Mockery::mock( BaseRepository::class );
		$calls      = 0;

		RepositoryRegistry::register(
			'Some\\Model',
			static function () use ( $repository, &$calls ) {
				++$calls;

				return $repository;
			}
		);

		$this->assertSame( 0, $calls );
		$this->assertSame( $repository, RepositoryRegistry::get( 'Some\\Model' ) );
		$this->assertSame( $repository, RepositoryRegistry::get( 'Some\\Model' ) );
		$this->assertSame( 1, $calls );
	}

	public function test_registering_again_replaces_the_factory(): void {
		$first  = Mockery::mock( BaseRepository::class );
		$second = Mockery::mock( BaseRepository::class );

		RepositoryRegistry::register( 'Some\\Model', static fn() => $first );
		RepositoryRegistry::register( 'Some\\Model', static fn() => $second );

		$this->assertSame( $second, RepositoryRegistry::get( 'Some\\Model' ) );
	}

	public function test_an_unregistered_model_throws(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'No repository registered for model: Missing\\Model' );

		RepositoryRegistry::get( 'Missing\\Model' );
	}

	public function test_a_factory_that_returns_something_else_throws(): void {
		RepositoryRegistry::register( 'Some\\Model', static fn() => new \stdClass() );

		$this->expectException( \RuntimeException::class );

		RepositoryRegistry::get( 'Some\\Model' );
	}
}
