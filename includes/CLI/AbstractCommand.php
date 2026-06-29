<?php

namespace WPO\AOM\CLI;

defined( 'ABSPATH' ) || exit;

abstract class AbstractCommand implements CommandInterface {
	/**
	 * Top-level WP-CLI namespace for all plugin commands (e.g. `wp aom ...`).
	 *
	 * @var string
	 */
	private string $command_prefix = 'aom';

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return $this->command_prefix . ' ' . $this->get_command_name();
	}

	/**
	 * The command's own name, appended to the prefix (e.g. "generate-tasks").
	 *
	 * @return string
	 */
	abstract protected function get_command_name(): string;
}