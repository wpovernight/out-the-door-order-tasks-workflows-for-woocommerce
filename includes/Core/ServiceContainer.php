<?php

namespace WPO\AOM\Core;

use InvalidArgumentException;
use WPO\AOM\Models\CustomOrderStatus;
use WPO\AOM\Models\Task;
use WPO\AOM\Models\TaskField;
use WPO\AOM\Models\TaskFieldOption;
use WPO\AOM\Models\TaskFieldValue;
use WPO\AOM\Repositories\CustomOrderStatusRepository;
use WPO\AOM\Repositories\RepositoryRegistry;
use WPO\AOM\Repositories\TaskRepository;
use WPO\AOM\Repositories\TaskFieldRepository;
use WPO\AOM\Repositories\TaskFieldOptionRepository;
use WPO\AOM\Repositories\TaskFieldValueRepository;
use WPO\AOM\REST\CustomOrderStatusController;
use WPO\AOM\REST\FulfillmentController;
use WPO\AOM\REST\TaskController;
use WPO\AOM\Services\CustomOrderStatusService;
use WPO\AOM\Services\FulfillmentService;
use WPO\AOM\Services\EmailService;
use WPO\AOM\Services\TaskManagerService;
use WPO\AOM\Admin as Admin;
use WPO\AOM\Services\TaskManagerSettingsService;
use WPO\AOM\Services\TaskStatusRoleService;

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
	 * Class names should match the build method suffix, e.g. 'TaskManagerService' => build_TaskManagerService()
	 *
	 * Order matters: services are resolved and their register() methods called in
	 * the order listed here. A service that depends on another (e.g. via its build
	 * method) should be listed AFTER the service it depends on, so the dependency
	 * has finished its register()-time setup before the dependent uses it.
	 *
	 * @var array
	 */
	private static array $service_map = array(
		// Services
		// Note: TaskManagerService depends on TaskStatusRoleService (via constructor),
		// which depends on TaskManagerSettingsService. Listed in dependency order.
		'TaskManagerSettingsService'  => TaskManagerSettingsService::class,
		'TaskStatusRoleService'       => TaskStatusRoleService::class,
		'TaskManagerService'          => TaskManagerService::class,
		'FulfillmentService'          => FulfillmentService::class,
		'CustomOrderStatusService'    => CustomOrderStatusService::class,
		'EmailService'                => EmailService::class,
		// REST Controllers
		'TaskController'              => TaskController::class,
		'FulfillmentController'       => FulfillmentController::class,
		'CustomOrderStatusController' => CustomOrderStatusController::class,
		// Admin Screens
		'OrderManager_Screen'         => Admin\OrderManager\Screen::class,
		'OrderEdit_Screen'            => Admin\OrderEdit\Screen::class,
	);

	/**
	 * Default repository bindings.
	 *
	 * @var array<string, class-string>
	 */
	private static array $default_bindings = array(
		Task::class              => TaskRepository::class,
		TaskField::class         => TaskFieldRepository::class,
		TaskFieldOption::class   => TaskFieldOptionRepository::class,
		TaskFieldValue::class    => TaskFieldValueRepository::class,
		CustomOrderStatus::class => CustomOrderStatusRepository::class,
	);

	/**
	 * Register services and repository bindings.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->register_services();
		$this->register_repository_bindings();
	}

	/**
	 * Register service classes and call their register method if available.
	 *
	 * @return void
	 */
	private function register_services(): void {
		foreach ( $this->get_service_map() as $id => $class_string ) {
			$service = $this->resolve_service( $id, $class_string );

			if ( method_exists( $service, 'register' ) ) {
				$service->register();
			}
		}
	}

	/**
	 * Resolve a service instance, build it if it hasn’t been cached yet.
	 *
	 * @param string $id Service ID.
	 * @param string $class_string
	 *
	 * @return object
	 *
	 */
	public function resolve_service( string $id, string $class_string = '' ): object {
		// Remove namespace from ID if present.
		$id = ltrim( strrchr( $id, '\\' ), '\\' ) ?: $id;

		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		// Check for a builder callback.
		$callback = $this->get_service_builder_callback( $id );

		if ( is_callable( $callback ) ) {
			$this->instances[ $id ] = call_user_func( $callback );

			return $this->instances[ $id ];
		}

		// Fallback to internal build method.
		$build_method = 'build_' . $id;

		if ( method_exists( $this, $build_method ) ) {
			/**
			 * @uses build_TaskManagerService()
			 * @uses build_OrderEdit_Screen()
			 * @uses build_OrderManager_Screen()
			 * @uses build_TaskStatusRoleService()
			 */
			$this->instances[ $id ] = $this->{$build_method}();

			return $this->instances[ $id ];
		}

		// Create a new instance.
		if ( class_exists( $class_string ) ) {
			$this->instances[ $id ] = new $class_string();

			return $this->instances[ $id ];
		}

		throw new InvalidArgumentException( sprintf( 'Service ID "%s" is not defined.', esc_html( $id ) ) );
	}

	/**
	 * Retrieve a builder callback for a service ID via filter.
	 *
	 * @param string $id Service ID.
	 *
	 * @return callable|null
	 */
	protected function get_service_builder_callback( string $id ): ?callable {
		/**
		 * Filters the Advanced Order Manager service builders.
		 *
		 * @param array<string, callable> $builders Associative array of service ID => callback.
		 */
		$builders = apply_filters( 'wpo_aom_service_builders', array(
			// Example: 'service_id' => fn() => new ServiceClass(),
		) );

		if ( isset( $builders[ $id ] ) ) {
			$callback = $builders[ $id ];

			if ( is_callable( $callback ) ) {
				return $callback;
			}

			_doing_it_wrong(
				__METHOD__,
				sprintf( 'Builder callback for service ID "%s" is not callable.', esc_html( $id ) ),
				'1.0.0'
			);
		}

		return null;
	}

	/**
	 * Get the service map, allowing filter overrides.
	 *
	 * @return array<string, class-string>
	 */
	private function get_service_map(): array {
		/**
		 * Filters the Advanced Order Manager service map.
		 *
		 * @param array<string, class-string> $map Service map.
		 */
		return (array) apply_filters( 'wpo_aom_service_map', self::$service_map );
	}

	/**
	 * Register model-to-repository bindings in the global registry.
	 *
	 * @return void
	 */
	private function register_repository_bindings(): void {
		/**
		 * Filters the Advanced Order Manager repository bindings.
		 *
		 * @param array<string, class-string> $bindings Repository bindings.
		 *
		 * @example
		 * array(
		 *    ModelClass::class => CustomRepositoryClass::class,
		 * );
		 *
		 * @return array<string, class-string>
		 */
		$bindings = (array) apply_filters( 'wpo_aom_repository_bindings', self::$default_bindings );

		foreach ( $bindings as $model => $repository ) {
			RepositoryRegistry::register( $model, fn() => new $repository() );
		}
	}

	/**
	 * Build and return an instance of TaskManagerService.
	 *
	 * @return TaskManagerService
	 */
	private function build_TaskManagerService(): TaskManagerService {
		return new TaskManagerService(
			new TaskRepository(),
			new TaskFieldRepository(),
			new TaskFieldOptionRepository(),
			new TaskFieldValueRepository(),
			$this->resolve_service( 'TaskStatusRoleService', TaskStatusRoleService::class )
		);
	}

	/**
	 * Build and return an instance of OrderEdit MetaBox.
	 *
	 * @return Admin\OrderEdit\Screen
	 */
	private function build_OrderEdit_Screen(): Admin\OrderEdit\Screen {
		return new Admin\OrderEdit\Screen(
			$this->resolve_service( 'FulfillmentService', FulfillmentService::class ),
			$this->resolve_service( 'TaskStatusRoleService', TaskStatusRoleService::class )
		);
	}

	/**
	 * Build and return an instance of OrderManager Screen.
	 *
	 * @return Admin\OrderManager\Screen
	 */
	private function build_OrderManager_Screen(): Admin\OrderManager\Screen {
		return new Admin\OrderManager\Screen(
			$this->resolve_service( 'TaskStatusRoleService', TaskStatusRoleService::class )
		);
	}

	/**
	 * Build and return an instance of TaskStatusRoleService.
	 *
	 * @return TaskStatusRoleService
	 */
	private function build_TaskStatusRoleService(): TaskStatusRoleService {
		return new TaskStatusRoleService(
			$this->resolve_service( 'TaskManagerSettingsService', TaskManagerSettingsService::class ),
			new TaskFieldOptionRepository(),
		);
	}
}
