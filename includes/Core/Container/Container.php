<?php

namespace WPO\OTD\Core\Container;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;

defined( 'ABSPATH' ) || exit;

final class Container implements ContainerInterface {
	/**
	 * Factory closures keyed by service id.
	 *
	 * @var array<string, Closure>
	 */
	private array $bindings = array();

	/**
	 * Whether a given id should be resolved once and shared (singleton).
	 *
	 * @var array<string, bool>
	 */
	private array $shared = array();

	/**
	 * Resolved singleton instances, keyed by id.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Lazy loaders for deferred providers, keyed by a promised service id.
	 *
	 * Resolving such an id runs the loader (which registers the owning provider)
	 * before the id is constructed. Populated by the Kernel.
	 *
	 * @var array<string, Closure>
	 */
	private array $deferred = array();

	/**
	 * Ids currently mid-construction, used to detect dependency cycles.
	 *
	 * @var array<string, true>
	 */
	private array $resolving = array();

	/**
	 * Bind a definition.
	 *
	 * @param string       $id      Fully-qualified class/interface name.
	 * @param Closure|null $factory Factory receiving the container; null = autowire.
	 * @param bool         $shared  Whether to cache the resolved instance (singleton).
	 *
	 * @return void
	 */
	public function bind( string $id, ?Closure $factory = null, bool $shared = true ): void {
		$this->bindings[ $id ] = $factory ?? $this->autowire( $id );
		$this->shared[ $id ]   = $shared;

		// Drop any cached singleton so a re-bind takes effect on the next get().
		unset( $this->instances[ $id ] );
	}

	/**
	 * Bind a shared (singleton) definition.
	 *
	 * @param string       $id      Fully-qualified class/interface name.
	 * @param Closure|null $factory Factory receiving the container; null = autowire.
	 *
	 * @return void
	 */
	public function singleton( string $id, ?Closure $factory = null ): void {
		$this->bind( $id, $factory, true );
	}

	/**
	 * Alias an abstraction (usually an interface) to a concrete id.
	 *
	 * Lets callers depend on `SomeInterface::class` while the container resolves
	 * a concrete implementation. The seam that makes substitution possible.
	 *
	 * @param string $abstract Interface or alias id.
	 * @param string $concrete Concrete id to resolve instead.
	 *
	 * @return void
	 */
	public function alias( string $abstract, string $concrete ): void {
		$this->bind( $abstract, fn( Container $c ) => $c->get( $concrete ), true );
	}

	/**
	 * Register a lazy loader for a deferred provider's promised id.
	 *
	 * @param string  $id     Service id the deferred provider binds.
	 * @param Closure $loader Receives the container; registers the provider.
	 *
	 * @return void
	 */
	public function defer( string $id, Closure $loader ): void {
		$this->deferred[ $id ] = $loader;
	}

	/**
	 * Drop deferred loaders for the given ids (once their provider has loaded).
	 *
	 * @param string[] $ids Service ids to remove from the deferred map.
	 *
	 * @return void
	 */
	public function forget_deferred( array $ids ): void {
		foreach ( $ids as $id ) {
			unset( $this->deferred[ $id ] );
		}
	}

	/**
	 * Whether the container has been "configured" to provide the given id.
	 *
	 * @param string $id Service id.
	 *
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->bindings[ $id ] ) ||
		       isset( $this->instances[ $id ] ) ||
		       isset( $this->deferred[ $id ] );
	}

	/**
	 * Resolve a service.
	 *
	 * @template T of object
	 * @param class-string<T> $id Service id.
	 *
	 * @return T
	 */
	public function get( string $id ): mixed {
		// Return the shared instance if this id has already been resolved.
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		// First call for a deferred provider's id, load the provider now.
		// Its loader calls register() (and boot(), if the boot phase has started),
		// which adds the real binding.
		if ( ! isset( $this->bindings[ $id ] ) && isset( $this->deferred[ $id ] ) ) {
			( $this->deferred[ $id ] )( $this );

			// That loader may itself resolve this same id while running, and if it
			// does, the instance is already cached. Re-check the cache here so we
			// return that one instance instead of constructing a duplicate below.
			if ( isset( $this->instances[ $id ] ) ) {
				return $this->instances[ $id ];
			}
		}

		// This id is already mid-construction higher up the call stack, so
		// resolving it again means its dependency graph forms a cycle. Bail
		// instead of recursing into a stack overflow.
		if ( isset( $this->resolving[ $id ] ) ) {
			throw new ContainerException(
				esc_html(
					sprintf(
						'Circular dependency detected while resolving "%s" (chain: %s).',
						$id,
						implode( ' -> ', array_keys( $this->resolving ) ) . ' -> ' . $id
					)
				)
			);
		}

		$this->resolving[ $id ] = true;

		try {
			$factory = $this->bindings[ $id ] ?? $this->autowire( $id );
			$object  = $factory( $this );
		} finally {
			unset( $this->resolving[ $id ] );
		}

		// Cache the instance for reuse unless this id was bound as non-shared.
		if ( $this->shared[ $id ] ?? true ) {
			$this->instances[ $id ] = $object;
		}

		return $object;
	}

	/**
	 * Build a factory that constructs $id via its constructor type-hints.
	 *
	 * @param string $id Fully-qualified class name.
	 *
	 * @return Closure
	 */
	private function autowire( string $id ): Closure {
		return function ( Container $container ) use ( $id ) {
			if ( ! class_exists( $id ) ) {
				throw new NotFoundException(
					esc_html( sprintf( 'Service "%s" is not bound and is not an instantiable class.', $id ) )
				);
			}

			$reflector   = new ReflectionClass( $id );
			$constructor = $reflector->getConstructor();

			if ( null === $constructor ) {
				return new $id();
			}

			$args = array();

			foreach ( $constructor->getParameters() as $param ) {
				$type = $param->getType();

				if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
					// A class dependency. Resolve it recursively.
					$args[] = $container->get( $type->getName() );
				} elseif ( $param->isDefaultValueAvailable() ) {
					$args[] = $param->getDefaultValue();
				} elseif ( $param->allowsNull() ) {
					$args[] = null;
				} else {
					throw new ContainerException(
						esc_html( sprintf( 'Cannot autowire parameter "$%s" of %s.', $param->getName(), $id ) )
					);
				}
			}

			return $reflector->newInstanceArgs( $args );
		};
	}
}
