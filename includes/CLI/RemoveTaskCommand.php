<?php

namespace WPO\AOM\CLI;

use WPO\AOM\Repositories\TaskFieldValueRepository;
use WPO\AOM\Repositories\TaskRepository;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

final class RemoveTaskCommand extends AbstractCommand {
	private TaskRepository $task_repository;
	private TaskFieldValueRepository $task_field_value_repository;

	/**
	 * Constructor.
	 *
	 * @param TaskRepository           $task_repository
	 * @param TaskFieldValueRepository $task_field_value_repository
	 */
	public function __construct(
		TaskRepository $task_repository,
		TaskFieldValueRepository $task_field_value_repository
	) {
		$this->task_repository = $task_repository;
		$this->task_field_value_repository = $task_field_value_repository;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_command_name(): string {
		return 'tasks remove';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Removes all tasks (and their field values).';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_synopsis(): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function __invoke( array $arguments, array $options ): void {
		$tasks = $this->task_repository->get();
		$count = count( $tasks );

		if ( 0 === $count ) {
			WP_CLI::success( 'No tasks to remove.' );

			return;
		}

		// Honours the global --yes flag; prompts otherwise.
		WP_CLI::confirm(
			sprintf( 'Delete all %d task(s)? Their field values are removed too.', $count ),
			$options
		);

		foreach ( $tasks as $task ) {
			$this->task_repository->delete( (int) $task->id );
		}

		// Field values are cascade-deleted with their task via the FK constraint;
		// sweep any that remain in case FK enforcement is disabled on this DB.
		foreach ( $this->task_field_value_repository->get() as $field_value ) {
			$this->task_field_value_repository->delete( (int) $field_value->id );
		}

		WP_CLI::success( sprintf( '%d task(s) removed.', $count ) );
	}
}
