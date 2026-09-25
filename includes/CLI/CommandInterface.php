<?php

namespace WPO\OTD\CLI;

defined( 'ABSPATH' ) || exit;

interface CommandInterface {
	/**
	 * Run the command.
	 *
	 * @param array<int, string>    $arguments Positional arguments.
	 * @param array<string, string> $options   Associative (--flag) arguments.
	 *
	 * @return void
	 */
	public function __invoke( array $arguments, array $options ): void;

	/**
	 * The full WP-CLI command name (e.g. "aom generate-tasks").
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Short description shown in `wp help`.
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * WP-CLI synopsis describing the command's arguments.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_synopsis(): array;
}