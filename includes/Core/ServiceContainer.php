<?php

namespace WPO\AOM\Core;

use InvalidArgumentException;
use WPO\AOM\AdvancedOrderManager;
use WPO\AOM\Models\Task;
use WPO\AOM\Repositories\RepositoryRegistry;
use WPO\AOM\Repositories\TaskFieldOptionRepository;
use WPO\AOM\Repositories\TaskFieldRepository;
use WPO\AOM\Repositories\TaskFieldValueRepository;
use WPO\AOM\Repositories\TaskRepository;
use WPO\AOM\Services\TaskService;

defined( 'ABSPATH' ) || exit;

final class ServiceContainer {
	/**
	 * Internal service instance cache.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Register repositories and services.
	 *
	 * @param AdvancedOrderManager $plugin Plugin instance.
	 *
	 * @return void
	 */
	public function register( AdvancedOrderManager $plugin ) {
		$this->register_services( $plugin );
		$this->register_repository_bindings();
	}

	/**
	 * Register service classes and optionally assign them to plugin properties.
	 *
	 * @param AdvancedOrderManager $plugin Plugin instance.
	 *
	 * @return void
	 */
	private function register_services( AdvancedOrderManager $plugin ): void {
		foreach ( $this->service_map() as $property => $definition ) {
			[ $id, $store ] = $definition;

			$service = $this->resolve_service( $id );

			if ( method_exists( $service, 'register' ) ) {
				$service->register();
			}

			// Store the service in a dynamic property if specified.
			if ( $store && ! property_exists( $this, $property ) ) {
				/* @phpstan-ignore-next-line Suppressing type warning for dynamic property assignment. */
				$plugin->{$property} = $service;
			}
		}
	}

	/**
	 * Resolve a service instance (builds it once and caches it).
	 *
	 * @param string $id Service ID.
	 *
	 * @return object
	 *
	 * @throws InvalidArgumentException If the service ID is not defined.
	 */
	public function resolve_service( string $id ): object {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		$build_method = 'build_' . $id;

		if ( ! method_exists( $this, $build_method ) ) {
			throw new InvalidArgumentException( "Service ID '{$id}' is not defined." );
		}

		/** @uses build_task_management_service() */
		$this->instances[ $id ] = $this->{$build_method}();

		return $this->instances[ $id ];
	}

	/**
	 * Define the map of plugin properties to service IDs.
	 *
	 * @return array<string, array{string,bool}>
	 */
	private function service_map(): array {
		$map = array(
			'task_management_service' => array( 'task_management_service', true ),
		);

		/**
		 * Filters the Advanced Order Manager service map.
		 *
		 * @param array<string, array{string,bool}> $map Service map.
		 */
		return (array) apply_filters( 'wpo_aom_service_map', $map );
	}

	/**
	 * Register repository bindings.
	 *
	 * @return void
	 */
	private function register_repository_bindings(): void {
		RepositoryRegistry::register( Task::class, fn() => new TaskRepository() );
	}

	/**
	 * Build the TaskService.
	 *
	 * @return TaskService
	 */
	private function build_task_management_service(): TaskService {
		return new TaskService(
			new TaskRepository(),
			new TaskFieldRepository(),
			new TaskFieldOptionRepository(),
			new TaskFieldValueRepository()
		);
	}
}
