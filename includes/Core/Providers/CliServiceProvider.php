<?php

namespace WPO\OTD\Core\Providers;

use WP_CLI;
use WPO\OTD\CLI\CommandInterface;
use WPO\OTD\CLI\FulfillmentsClearCommand;
use WPO\OTD\CLI\InstallCommand;
use WPO\OTD\CLI\OptionsClearCommand;
use WPO\OTD\CLI\TasksGenerateCommand;
use WPO\OTD\CLI\TasksRemoveCommand;
use WPO\OTD\Core\Container\Container;
use WPO\OTD\Core\Container\ServiceProvider;

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
