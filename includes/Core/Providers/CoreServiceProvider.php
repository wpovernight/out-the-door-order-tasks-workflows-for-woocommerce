<?php

namespace WPO\AOM\Core\Providers;

use WPO\AOM\Core\Container\Container;
use WPO\AOM\Core\Container\ServiceProvider;
use WPO\AOM\Core\Installer;
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
use WPO\AOM\Services\EmailService;
use WPO\AOM\Services\CustomOrderStatusService;
use WPO\AOM\Services\TaskManagerService;
use WPO\AOM\REST\TaskController;
use WPO\AOM\REST\FulfillmentController;
use WPO\AOM\REST\CustomOrderStatusController;
use WPO\AOM\Admin\OrderManager\Screen as OrderManagerScreen;
use WPO\AOM\Admin\OrderEdit\Screen as OrderEditScreen;

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
