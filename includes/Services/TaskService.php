<?php

namespace WPO\AOM\Services;

use Exception;
use WPO\AOM\Models\Task;
use WPO\AOM\Models\TaskField;
use WPO\AOM\Models\TaskFieldValue;
use WPO\AOM\Repositories\TaskFieldOptionRepository;
use WPO\AOM\Repositories\TaskFieldRepository;
use WPO\AOM\Repositories\TaskFieldValueRepository;
use WPO\AOM\Repositories\TaskRepository;

defined( 'ABSPATH' ) || exit;

final class TaskService {
	protected TaskRepository $task_repository;
	protected TaskFieldRepository $task_field_repository;
	protected TaskFieldOptionRepository $task_field_option_repository;
	protected TaskFieldValueRepository $task_field_value_repository;

	/**
	 * Constructor.
	 */
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

	/** ================================
	 *   Task Methods
	 *  ================================ */

	/**
	 * Returns all Tasks.
	 *
	 * @return array<int, Task>
	 */
	public function get_all_tasks( bool $with_fields ): array {
		return $this->task_repository->get();
	}

	/**
	 * Get all tasks along with their associated fields and values.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_all_tasks_with_fields(): array {
		$tasks = $this->task_repository->get();
		if ( empty( $tasks ) ) {
			return array();
		}

		// Fetch all fields and values.
		$all_fields = $this->get_all_fields();
		$all_values = $this->task_field_value_repository->get();

		// Fetch select-type fields and their options.
		$select_fields        = array_filter( $all_fields, fn( $field ) => 'select' === $field->type );
		$select_field_ids     = array_map( fn( $field ) => $field->id, $select_fields );
		$select_field_options = $this->task_field_option_repository->find_all_by_in( 'field_id', $select_field_ids );

		// Group values and options for easy lookup.
		$values_by_task_and_field = array();
		foreach ( $all_values as $value ) {
			$values_by_task_and_field[ $value->task_id ][ $value->field_id ] = $value;
		}

		// Group options by field ID for easy lookup.
		$options_by_field = array();
		foreach ( $select_field_options as $option ) {
			$options_by_field[ $option->field_id ][] = $option->to_array();
		}

		// Assemble final task data with fields and values.
		$result = array();
		foreach ( $tasks as $task ) {
			$task_fields = array();

			foreach ( $all_fields as $field ) {
				$field_value   = $values_by_task_and_field[ $task->id ][ $field->id ] ?? null;
				$field_options = 'select' === $field->type ? ( $options_by_field[ $field->id ] ?? array() ) : array();

				$task_fields[] = array_merge(
					$field->to_array(),
					array(
						'value'   => $field_value->value ?? null,
						'options' => $field_options,
					),
				);
			}

			$result[] = array_merge( $task->to_array(), array( 'fields' => $task_fields ) );
		}

		return $result;
	}

	/**
	 * Get a Task along with its associated fields and their values.
	 *
	 * @param int $task_id
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_task_with_fields( int $task_id ): ?array {
		$task = $this->task_repository->find( $task_id );
		if ( ! $task ) {
			return null;
		}

		$fields             = $this->get_all_fields();
		$values             = $this->get_field_values_for_task( $task_id );
		$values_by_field_id = array_column( $values, null, 'field_id' );
		$task_fields        = array();

		// Map field values to their definitions
		foreach ( $fields as $field ) {
			$field_value = $values_by_field_id[ $field->id ]->value ?? null;

			$field_options = 'select' === $field->type
				? $this->task_field_option_repository->find_by( 'field_id', $field->id )
				: array();

			$task_fields[] = array_merge(
				$field->to_array(),
				array(
					'value'   => $field_value,
					'options' => $field_options ?? array(),
				)
			);
		}

		return array_merge( $task->to_array(), array( 'fields' => $task_fields ) );
	}

	/**
	 * Get a Task by ID.
	 *
	 * @param int $task_id
	 *
	 * @return Task|null
	 */
	public function get_task( int $task_id ): ?Task {
		return $this->task_repository->find( $task_id );
	}

	/**
	 * Create a new Task.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int|false Inserted ID or false on failure
	 *
	 * @throws Exception
	 */
	public function create_task( array $data ): Task {
		$task = new Task( $data );

		return $this->task_repository->save( $task );
	}

	/**
	 * Update a Task by ID.
	 *
	 * @param int $task_id
	 * @param array<string, mixed> $data
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function update_task( int $task_id, array $data ): bool {
		$task = $this->task_repository->find( $task_id );
		if ( ! $task ) {
			return false;
		}

		$task->fill( $data );

		return (bool) $this->task_repository->save( $task );
	}

	/**
	 * Delete a Task by ID.
	 *
	 * @param int $task_id
	 *
	 * @return bool
	 */
	public function delete_task( int $task_id ): bool {
		return $this->task_repository->where( 'id', $task_id )->delete();
	}

	/** ================================
	 *   Task Field Methods
	 *  ================================ */

	/**
	 * Create a new Task Field.
	 *
	 * @param array $data
	 *
	 * @return int
	 */
	public function create_field( array $data ): int {
		$field = new TaskField( $data );

		return $this->task_field_repository->save( $field );
	}

	/**
	 * Update a Task Field by ID.
	 *
	 * @param int $field_id
	 * @param array $data
	 *
	 * @return bool
	 */
	public function update_field( int $field_id, array $data ): bool {
		$field = $this->task_field_repository->find( $field_id );
		if ( ! $field ) {
			return false;
		}

		$field->fill( $data );

		return (bool) $this->task_field_repository->save( $field );
	}

	/**
	 * Delete a Task Field by ID.
	 *
	 * @param int $field_id
	 *
	 * @return bool
	 */
	public function delete_field( int $field_id ): bool {
		return $this->task_field_repository->where( 'id', $field_id )->delete();
	}

	/**
	 * Get all Task Fields.
	 *
	 * @return array<int, TaskField>
	 */
	public function get_all_fields(): array {
		return $this->task_field_repository->get();
	}

	/** ================================
	 *   Task Field Option Methods
	 *  ================================ */

	/**
	 * Add an option to a select-type field.
	 *
	 * @param int $field_id
	 * @param array $option_data
	 *
	 * @return int
	 */
	public function add_option_to_field( int $field_id, array $option_data ): int {
		$option_data['field_id'] = $field_id;

		return $this->task_field_option_repository->insert( $option_data );
	}

	/**
	 * Update an option by ID.
	 *
	 * @param int $option_id
	 * @param array $option_data
	 *
	 * @return bool
	 */
	public function update_option( int $option_id, array $option_data ): bool {
		$option = $this->task_field_option_repository->find( $option_id );
		if ( ! $option ) {
			return false;
		}

		$option->fill( $option_data );

		return (bool) $this->task_field_option_repository->save( $option );
	}

	/**
	 * Delete an option by ID.
	 *
	 * @param int $option_id
	 *
	 * @return bool
	 */
	public function delete_option( int $option_id ): bool {
		return $this->task_field_option_repository->where( 'id', $option_id )->delete();
	}

	/** ================================
	 *   Task Field Value Methods
	 *  ================================ */

	/**
	 * Get all field values for a specific task.
	 *
	 * @param int $task_id
	 *
	 * @return array<int, TaskFieldValue>
	 */
	public function get_field_values_for_task( int $task_id ): array {
		return $this->task_field_value_repository->find_all_by( 'task_id', $task_id );
	}

	/**
	 * Set or update a field value for a specific task.
	 *
	 * @param int $task_id
	 * @param int $field_id
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public function set_field_value( int $task_id, int $field_id, $value ): bool {
		$field_value = $this->task_field_value_repository->find_by_task_and_field( $task_id, $field_id );

		if ( $field_value ) {
			$field_value->value = maybe_serialize( $value );

			return (bool) $this->task_field_value_repository->save( $field_value );
		}

		$new_value = array(
			'task_id'  => $task_id,
			'field_id' => $field_id,
			'value'    => maybe_serialize( $value ),
		);

		return $this->task_field_value_repository->insert( $new_value );
	}

	/**
	 * Update multiple field values for a specific task.
	 *
	 * @param int $task_id
	 * @param array $field_data
	 *
	 * @return bool
	 */
	public function update_field_values( int $task_id, array $field_data ): bool {
		$success = true;

		foreach ( $field_data as $field_id => $value ) {
			$result = $this->set_field_value( $task_id, (int) $field_id, $value );
			if ( ! $result ) {
				$success = false;
			}
		}

		return $success;
	}

	/** ================================
	 *   Additional Task Operations
	 *  ================================ */

	/**
	 * Assign a task to a user by setting the "Assignee" field.
	 *
	 * @param int $task_id
	 * @param int $user_id
	 *
	 * @return bool
	 */
	public function assign_task_to_user( int $task_id, int $user_id ): bool {
		$field = $this->task_field_repository->find_by_label( 'Assignee' );

		if ( ! $field ) {
			return false;
		}

		// Check if user exists.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		return $this->set_field_value( $task_id, $field->id, $user_id );
	}

}
