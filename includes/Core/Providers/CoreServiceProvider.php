<?php

namespace WPO\OTD\Core\Providers;

use WPO\OTD\Core\Container\Container;
use WPO\OTD\Core\Container\ServiceProvider;
use WPO\OTD\Core\Installer;
use WPO\OTD\Models\CustomOrderStatus;
use WPO\OTD\Models\Task;
use WPO\OTD\Models\TaskField;
use WPO\OTD\Models\TaskFieldOption;
use WPO\OTD\Models\TaskFieldValue;
use WPO\OTD\Repositories\CustomOrderStatusRepository;
use WPO\OTD\Repositories\RepositoryRegistry;
use WPO\OTD\Repositories\TaskRepository;
use WPO\OTD\Repositories\TaskFieldRepository;
use WPO\OTD\Repositories\TaskFieldOptionRepository;
use WPO\OTD\Repositories\TaskFieldValueRepository;
use WPO\OTD\Services\EmailService;
use WPO\OTD\Services\CustomOrderStatusService;
use WPO\OTD\Services\TaskManagerService;
use WPO\OTD\REST\TaskController;
use WPO\OTD\REST\FulfillmentController;
use WPO\OTD\REST\CustomOrderStatusController;
use WPO\OTD\Admin\OrderManager\Screen as OrderManagerScreen;
use WPO\OTD\Admin\OrderEdit\Screen as OrderEditScreen;

defined( 'ABSPATH' ) || exit;

final class CoreServiceProvider implements ServiceProvider {
	/**
	 * Model -> repository bindings for the RepositoryRegistry.
	 *
	 * @var array<class-string, class-string>
	 */
	private const REPOSITORY_BINDINGS = array(
		Task::class              => TaskRepository::class,
		TaskField::class         => TaskFieldRepository::class,
		TaskFieldOption::class   => TaskFieldOptionRepository::class,
		TaskFieldValue::class    => TaskFieldValueRepository::class,
		CustomOrderStatus::class => CustomOrderStatusRepository::class,
	);

	/**
	 * {@inheritDoc}
	 *
	 * Most services need NO explicit binding, as the container autowires any class
	 * whose constructor depends on other resolvable classes.
	 */
	public function register( Container $container ): void {
		foreach ( self::REPOSITORY_BINDINGS as $model => $repository ) {
			RepositoryRegistry::register(
				$model,
				static fn() => $container->get( $repository )
			);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		// REST controllers
		add_action(
			'rest_api_init',
			static function () use ( $container ) {
				$container->get( TaskController::class )->register_routes();
				$container->get( FulfillmentController::class )->register_routes();
				$container->get( CustomOrderStatusController::class )->register_routes();
			}
		);

		// Upgrade migrations
		add_action(
			'admin_init',
			static function () use ( $container ) {
				if ( Installer::is_upgrade_due() ) {
					$container->get( Installer::class )->upgrade();
				}
			}
		);

		// Admin screens
		if ( is_admin() ) {
			$container->get( OrderManagerScreen::class )->register_hooks();
			$container->get( OrderEditScreen::class )->register_hooks();
		}

		// Services that always hook in, regardless of request context.
		$container->get( TaskManagerService::class )->register_hooks();
		$container->get( EmailService::class )->register_hooks();
		$container->get( CustomOrderStatusService::class )->register_hooks();
	}
}
