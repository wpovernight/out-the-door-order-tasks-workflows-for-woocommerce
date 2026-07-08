<?php

namespace WPO\AOM\Core\Providers;

use WP_CLI;
use WPO\AOM\CLI\CommandInterface;
use WPO\AOM\CLI\FulfillmentsClearCommand;
use WPO\AOM\CLI\InstallCommand;
use WPO\AOM\CLI\OptionsClearCommand;
use WPO\AOM\CLI\TasksGenerateCommand;
use WPO\AOM\CLI\TasksRemoveCommand;
use WPO\AOM\Core\Container\Container;
use WPO\AOM\Core\Container\ServiceProvider;

defined( 'ABSPATH' ) || exit;

final class CliServiceProvider implements ServiceProvider {
	/**
	 * Command classes to register with WP-CLI.
	 *
	 * @var array<int, class-string<CommandInterface>>
	 */
	private const COMMANDS = array(
		InstallCommand::class,
		TasksGenerateCommand::class,
		TasksRemoveCommand::class,
		FulfillmentsClearCommand::class,
		OptionsClearCommand::class,
	);

	/**
	 * {@inheritDoc}
	 */
	public function register( Container $container ): void {
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot( Container $container ): void {
		add_action(
			'cli_init',
			function () use ( $container ) {
				foreach ( self::COMMANDS as $command_class ) {
					$this->register_command( $container->get( $command_class ) );
				}
			}
		);
	}

	/**
	 * Register a single command with WP-CLI from its CommandInterface contract.
	 *
	 * @param CommandInterface $command
	 *
	 * @return void
	 */
	private function register_command( CommandInterface $command ): void {
		WP_CLI::add_command(
			$command->get_name(),
			$command,
			array(
				'shortdesc' => $command->get_description(),
				'synopsis'  => $command->get_synopsis(),
			)
		);
	}
}
