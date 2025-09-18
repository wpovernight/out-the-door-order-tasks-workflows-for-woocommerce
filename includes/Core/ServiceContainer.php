<?php

namespace WPO\AOM\Core;

use InvalidArgumentException;
use WPO\AOM\Models\Task;
use WPO\AOM\Models\TaskField;
use WPO\AOM\Models\TaskFieldOption;
use WPO\AOM\Models\TaskFieldValue;
use WPO\AOM\Repositories\RepositoryRegistry;
use WPO\AOM\Repositories\TaskFieldOptionRepository;
use WPO\AOM\Repositories\TaskFieldRepository;
use WPO\AOM\Repositories\TaskFieldValueRepository;
use WPO\AOM\Repositories\TaskRepository;
use WPO\AOM\REST\BaseRestController;
use WPO\AOM\Services\TaskManagementService;

defined( 'ABSPATH' ) || exit;

/**
 * The ServiceContainer class handles the registration and instantiation of services
 * and repository bindings within the Advanced Order Manager plugin.
 */
final class ServiceContainer {

	/**
	 * Internal service instance cache.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Static service map.
	 *
	 * @var array<string, class-string>
	 */
	private static array $service_map = array(
		'task_management_service' => TaskManagementService::class,
	);

	/**
	 * Default repository bindings.
	 *
	 * @var array<string, string>
	 */
	private static array $default_bindings = array(
		Task::class            => TaskRepository::class,
		TaskField::class       => TaskFieldRepository::class,
		TaskFieldOption::class => TaskFieldOptionRepository::class,
		TaskFieldValue::class  => TaskFieldValueRepository::class,
	);

	/**
	 * Register repositories and services.
	 *
	 * @return void
	 */
	public function register() {
		$this->register_services();
		$this->register_repository_bindings();
	}

	/**
	 * Register service classes and optionally assign them to plugin properties.
	 *
	 * @return void
	 */
	private function register_services(): void {
		foreach ( $this->service_map() as $id => $class ) {
			$service = $this->resolve_service( $id );

			if ( method_exists( $service, 'register' ) ) {
				$service->register();
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
		/**
		 * Filters the Advanced Order Manager service map.
		 *
		 * @param array<string, class-string> $map Service map.
		 */
		return (array) apply_filters( 'wpo_aom_service_map', self::$service_map );
	}

	/**
	 * Register repository bindings.
	 *
	 * @return void
	 */
	private function register_repository_bindings(): void {
		/**
		 * Filters the Advanced Order Manager repository bindings.
		 *
		 * @param array<string, string> $bindings Repository bindings.
		 */
		$bindings = apply_filters( 'wpo_aom_repository_bindings', self::$default_bindings );

		foreach ( $bindings as $model => $repository ) {
			RepositoryRegistry::register( $model, fn() => new $repository() );
		}
	}

	/**
	 * Build the TaskService.
	 *
	 * @return TaskManagementService
	 */
	private function build_task_management_service(): TaskManagementService {
		return new TaskManagementService(
			new TaskRepository(),
			new TaskFieldRepository(),
			new TaskFieldOptionRepository(),
			new TaskFieldValueRepository()
		);
	}
}
