<?php

namespace WPO\OTD\CLI;

use WP_CLI;
use WPO\OTD\Repositories\TaskFieldOptionRepository;
use WPO\OTD\Repositories\TaskFieldRepository;
use WPO\OTD\Repositories\TaskFieldValueRepository;
use WPO\OTD\Repositories\TaskRepository;

defined( 'ABSPATH' ) || exit;

final class TasksGenerateCommand extends AbstractCommand {
	private readonly TaskRepository $task_repository;
	private readonly TaskFieldRepository $task_field_repository;
	private readonly TaskFieldOptionRepository $task_field_option_repository;
	private readonly TaskFieldValueRepository $task_field_value_repository;

	public function __construct(
		TaskRepository $task_repository,
		TaskFieldRepository $task_field_repository,
		TaskFieldOptionRepository $task_field_option_repository,
		TaskFieldValueRepository $task_field_value_repository
	) {
		$this->task_repository              = $task_repository;
		$this->task_field_repository        = $task_field_repository;
		$this->task_field_option_repository = $task_field_option_repository;
		$this->task_field_value_repository  = $task_field_value_repository;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_command_name(): string {
		return 'tasks generate';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Generates sample tasks for development and testing.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_synopsis(): array {
		return array(
			array(
				'type'        => 'assoc',
				'name'        => 'count',
				'optional'    => true,
				'default'     => 10,
				'description' => 'Number of sample tasks to create.',
			),
			array(
				'type'        => 'assoc',
				'name'        => 'order',
				'optional'    => true,
				'description' => 'Order ID to associate with each task. Defaults to 0 (unassigned).',
			),
			array(
				'type'        => 'assoc',
				'name'        => 'user',
				'optional'    => true,
				'description' => 'User ID to set as the task creator. Defaults to 1.',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function __invoke( array $arguments, array $options ): void {
		$count    = isset( $options['count'] ) ? max( 1, (int) $options['count'] ) : 10;
		$order_id = isset( $options['order'] ) ? (int) $options['order'] : 0;
		$user_id  = isset( $options['user'] ) ? (int) $options['user'] : 1;

		$created = $this->seed_tasks( $count, $order_id, $user_id );

		WP_CLI::success( sprintf( '%d sample task(s) created.', $created ) );
	}

	/**
	 * Generate $count sample tasks with randomised status and priority.
	 *
	 * @return int Number of tasks created.
	 */
	private function seed_tasks( int $count, int $order_id, int $user_id ): int {
		$fields = array(
			'status'   => $this->field_id( 'status' ),
			'position' => $this->field_id( 'position' ),
			'creator'  => $this->field_id( 'creator' ),
			'order'    => $this->field_id( 'order' ),
			'priority' => $this->field_id( 'priority' ),
			'due_date' => $this->field_id( 'due_date' ),
		);

		$statuses = array(
			$this->option_id( 'not_started' ),
			$this->option_id( 'in_progress' ),
			$this->option_id( 'done' ),
		);

		$priorities = array(
			$this->option_id( 'low' ),
			$this->option_id( 'medium' ),
			$this->option_id( 'high' ),
			$this->option_id( 'critical' ),
		);

		// Track the next position per status so tasks stack within their column.
		$status_positions = array_fill_keys( $statuses, 0 );

		for ( $i = 1; $i <= $count; $i ++ ) {
			$status   = $statuses[ array_rand( $statuses ) ];
			$priority = $priorities[ array_rand( $priorities ) ];

			$status_positions[ $status ] ++;

			$this->insert_task(
				$fields,
				sprintf( 'Sample Task %d', $i ),
				sprintf( 'This is sample task number %d.', $i ),
				array(
					'status'   => $status,
					'position' => $status_positions[ $status ],
					'creator'  => $user_id,
					'order'    => $order_id,
					'priority' => $priority,
					'due_date' => gmdate( 'Y-m-d', strtotime( sprintf( '+%d days', wp_rand( 2, 10 ) ) ) ),
				)
			);
		}

		return $count;
	}

	/**
	 * Insert a single task and its field values.
	 *
	 * @param array<string, int> $fields Map of field slug => field ID.
	 * @param string $title
	 * @param string $description
	 * @param array<string, int|string> $values Map of field slug => value.
	 *
	 * @return void
	 */
	private function insert_task( array $fields, string $title, string $description, array $values ): void {
		$task_id = $this->task_repository->insert(
			array(
				'title'       => $title,
				'description' => $description,
			)
		);

		foreach ( $values as $slug => $value ) {
			if ( ! isset( $fields[ $slug ] ) ) {
				continue;
			}

			$this->task_field_value_repository->insert(
				array(
					'task_id'  => $task_id,
					'field_id' => $fields[ $slug ],
					'value'    => $value,
				)
			);
		}
	}

	/**
	 * Resolve a task field ID by slug, halting with an error if it is missing.
	 *
	 * @param string $slug
	 *
	 * @return int
	 */
	private function field_id( string $slug ): int {
		$field = $this->task_field_repository->find_by_slug( $slug );

		if ( ! $field ) {
			WP_CLI::error( sprintf( 'Task field "%s" not found. Is the plugin installed?', $slug ) );
		}

		return (int) $field->id;
	}

	/**
	 * Resolve a task field option ID by slug, halting with an error if it is missing.
	 *
	 * @param string $slug
	 *
	 * @return int
	 */
	private function option_id( string $slug ): int {
		$option = $this->task_field_option_repository->find_by_slug( $slug );

		if ( ! $option ) {
			WP_CLI::error( sprintf( 'Task field option "%s" not found. Is the plugin installed?', $slug ) );
		}

		return (int) $option->id;
	}
}
