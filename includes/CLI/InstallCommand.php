<?php

namespace WPO\AOM\CLI;

use WPO\AOM\Core\Installer;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

final class InstallCommand extends AbstractCommand {
	private readonly Installer $installer;

	/**
	 * Constructor.
	 *
	 * @param Installer $installer
	 */
	public function __construct( Installer $installer ) {
		$this->installer = $installer;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_command_name(): string {
		return 'install';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Installs the plugin database tables and default data.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_synopsis(): array {
		return array(
			array(
				'type'        => 'flag',
				'name'        => 'fresh',
				'optional'    => true,
				'description' => 'Drop all plugin tables first, then recreate and reseed them. Destroys all data.',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function __invoke( array $arguments, array $options ): void {
		if ( isset( $options['fresh'] ) ) {
			// Honours the global --yes flag; prompts otherwise.
			WP_CLI::confirm(
				'This drops ALL plugin tables and recreates them. All data will be lost. Continue?',
				$options
			);

			$this->installer->reset();
			WP_CLI::success( 'Plugin tables and options dropped, recreated, and reseeded.' );

			return;
		}

		// Non-fresh install is a no-op once the plugin is already installed.
		$this->installer->install();
		WP_CLI::success( 'Plugin tables and default data are in place.' );
	}
}