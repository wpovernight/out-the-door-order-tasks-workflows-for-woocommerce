<?php

namespace WPO\AOM\CLI;

use WPO\AOM\Core\Installer;
use WPO\AOM\Services\TaskManagerSettingsService;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

final class OptionsClearCommand extends AbstractCommand {
	/**
	 * {@inheritDoc}
	 */
	protected function get_command_name(): string {
		return 'options clear';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Removes every wp_options entry this plugin creates.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_synopsis(): array {
		return array();
	}

	/**
	 * The plugin option keys managed by this command.
	 *
	 * Pulled from their authoritative owners so the list can't drift from the
	 * code that actually writes the options.
	 *
	 * @return string[]
	 */
	private function option_names(): array {
		return array_merge(
			Installer::OPTION_NAMES,
			array( TaskManagerSettingsService::OPTION_NAME )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function __invoke( array $arguments, array $options ): void {
		$option_names = $this->option_names();

		// Honours the global --yes flag; prompts otherwise.
		WP_CLI::confirm(
			sprintf( 'Delete all %d plugin option(s)? This cannot be undone.', count( $option_names ) ),
			$options
		);

		$removed = 0;

		foreach ( $option_names as $option_name ) {
			// delete_option() returns false when the option didn't exist.
			// Only count the ones we actually cleared.
			if ( delete_option( $option_name ) ) {
				++$removed;
				WP_CLI::log( sprintf( 'Removed option: %s', $option_name ) );
			}
		}

		WP_CLI::success( sprintf( '%d plugin option(s) removed.', $removed ) );
	}
}
