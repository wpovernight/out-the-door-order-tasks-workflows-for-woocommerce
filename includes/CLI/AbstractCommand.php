<?php

namespace WPO\OTD\CLI;

defined( 'ABSPATH' ) || exit;

abstract class AbstractCommand implements CommandInterface {
	/**
	 * The command's name under the plugin's WP-CLI namespace, as in `wp otd ...`.
	 *
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'otd ' . $this->get_command_name();
	}

	/**
	 * The command's own name, appended to the namespace (e.g. "generate-tasks").
	 *
	 * @return string
	 */
	abstract protected function get_command_name(): string;
}