<?php

namespace WPO\AOM\Services;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use WPO\AOM\Enums\TaskFieldTypes;
use WPO\AOM\Models\Task;
use WPO\AOM\Models\TaskField;
use WPO\AOM\Models\TaskFieldOption;
use WPO\AOM\Models\TaskFieldValue;
use WPO\AOM\Repositories\TaskFieldOptionRepository;
use WPO\AOM\Repositories\TaskFieldRepository;
use WPO\AOM\Repositories\TaskFieldValueRepository;
use WPO\AOM\Repositories\TaskRepository;

defined( 'ABSPATH' ) || exit;

final class TaskManagerService {
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
	public function get_all_tasks(): array {
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

		// Group values and options for easy lookup.
		$values_by_task_and_field = array();
		foreach ( $all_values as $value ) {
			if ( ! isset( $values_by_task_and_field[ $value->task_id ][ $value->field_id ] ) ) {
				$values_by_task_and_field[ $value->task_id ][ $value->field_id ] = array();
			}
			$values_by_task_and_field[ $value->task_id ][ $value->field_id ][] = $value;
		}

		// Construct the result set.
		$result = array();
		foreach ( $tasks as $task ) {
			$task_fields = array();

			foreach ( $all_fields as $field ) {
				$field_value = array();

				if ( isset( $values_by_task_and_field[ $task->id ][ $field->id ] ) ) {
					// If multiple values exist for the same field, get all values.
					if ( is_array( $values_by_task_and_field[ $task->id ][ $field->id ] ) ) {
						foreach ( $values_by_task_and_field[ $task->id ][ $field->id ] as $single_value ) {
							$field_value[] = $this->get_field_value( $single_value, $field );
						}
					}
				}
				$task_fields[] = array_merge( $field->to_array(), array( 'values' => $field_value ?? null ) );
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

		// Map field values to their respective fields.
		foreach ( $fields as $field ) {
			$field_value   = $this->get_field_value( $values_by_field_id[ $field->id ], $field );
			$task_fields[] = array_merge( $field->to_array(), array( 'values' => $field_value ) );
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
	 * @return Task
	 *
	 * @throws Exception
	 */
	public function create_task( array $data ): Task {
		if ( empty( $data['title'] ) || ! is_string( $data['title'] ) ) {
			throw new Exception( 'Task title is required and must be a string.' );
		}

		$data['title']       = sanitize_text_field( $data['title'] );
		$data['description'] = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';

		$task = new Task( $data );

		$result   = $this->task_repository->save( $task );
		$task->id = $result;

		if ( ! $result ) {
			throw new Exception( 'Failed to create task.' );
		}

		return $task;
	}

	/**
	 * Update a Task by ID.
	 *
	 * @param int $task_id
	 * @param array<string, mixed> $data
	 *
	 * @return bool
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
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
	 * @throws RuntimeException
	 * @throws InvalidArgumentException
	 */
	public function delete_task( int $task_id ): bool {
		return $this->task_repository->delete( $task_id );
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
		return $this->task_field_repository->delete( $field_id );
	}

	/**
	 * Get all Task Fields.
	 *
	 * @return array<int, TaskField>
	 */
	public function get_all_fields( $index_by = 'slug' ): array {
		$fields = $this->task_field_repository->get();
		return array_column( $fields, null, $index_by );
	}

	/** ================================
	 *   Task Field Option Methods
	 *  ================================ */

	/**
	 * Get a specific option by ID.
	 *
	 * @param int $option_id
	 *
	 * @return TaskFieldOption|null
	 */
	public function get_option( int $option_id ): ?TaskFieldOption {
		return $this->task_field_option_repository->find( $option_id );
	}

	/**
	 * Get all options for a specific select-type field.
	 *
	 * @param int $field_id
	 *
	 * @return array
	 */
	public function get_options_for_field( int $field_id ): array {
		return $this->task_field_option_repository->find_all_by( 'field_id', $field_id );
	}

	/**
	 * Get all options for a specific select-type field by the field slug.
	 *
	 * @param string $slug
	 *
	 * @return array
	 */
	public function get_options_for_field_by_slug( string $slug ): array {
		$field = $this->task_field_repository->find_by_slug( $slug );
		if ( ! $field ) {
			return array();
		}

		return $this->get_options_for_field( $field->id );
	}

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
		return $this->task_field_option_repository->delete( $option_id );
	}

	/** ================================
	 *   Task Field Value Methods
	 *  ================================ */

	/**
	 * Get field values for a specific task, indexed by field ID.
	 *
	 * @param int $task_id
	 *
	 * @return array<int, TaskFieldValue>
	 */
	public function get_field_values_for_task( int $task_id ): array {
		$values = $this->task_field_value_repository->find_all_by( 'task_id', $task_id );
		return array_column( $values, null, 'field_id' );
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
	 * Move a task to a different position.
	 *
	 * @param int $task_id
	 * @param int $target_status_id
	 * @param int|null $previous_task_id
	 *
	 * @return float
	 */
	public function move_task( int $task_id, int $target_status_id, ?int $previous_task_id = null ): float {
		$fields         = $this->get_all_fields();
		$status_field_id   = $fields['status']->id ?? null;
		$position_field_id = $fields['position']->id ?? null;

		if ( ! $status_field_id || ! $position_field_id ) {
			return false;
		}

		// Check if the new status ID is valid.
		$target_status_option_field = $this->get_option( $target_status_id );
		if ( ! $target_status_option_field || $target_status_option_field->field_id !== $status_field_id ) {
			return false;
		}

		// If no previous task is specified, place at the start.
		$previous_position = 0.0;

		// Determine new position.
		if ( ! empty( $previous_task_id ) ) {
			$previous_task_values = $this->get_field_values_for_task( $previous_task_id );

			$previous_position = (float) ( $previous_task_values[ $position_field_id ]->value ?? 0.0 );
			$previous_status_id = (int) ( $previous_task_values[ $status_field_id ]->value ?? 0 );
		}

		$next_position_value = $this->task_repository->get_next_task_position(
			$previous_task_id,
			$target_status_id,
			$status_field_id,
			$position_field_id,
			$previous_position
		);
		$new_position        = $next_position_value
			? $this->calculate_fractional_position( $previous_position, $next_position_value )
			: $previous_position + 1.0;

		$new_position = apply_filters(
			'wpo_aom_task_calculated_new_position',
			(float) number_format( $new_position, 5, '.', '' ),
			$task_id,
			$previous_task_id,
			$target_status_id
		);

//		return $this->set_field_value( $task_id, $position_field->id, $new_position );
		return $this->set_field_value( $task_id, $position_field_id, $new_position );
		$this->set_field_value( $task_id, $position_field_id, $new_position );
		$update_data = array($position_field_id => $new_position,);
		// Update status
		if ( ! isset( $previous_status_id ) || $previous_status_id !== $target_status_id ) {
			$update_data[ $status_field_id ] = $target_status_id;
		}

		$result = $this->update_field_values( $task_id, $update_data );


		return $new_position;
	}

	/**
	 * Calculate a new fractional position between two positions.
	 *
	 * @param float $previous_position
	 * @param float $next_position
	 *
	 * @return float
	 */
	private function calculate_fractional_position( float $previous_position, float $next_position ): float {
		$position = ( $previous_position + $next_position ) / 2;

		$precision = 0.0001;
		// Check for precision issues.
		if ( abs( $next_position - $previous_position ) < $precision ) {
			// ToDo: Run rebalancing if positions are too close.
		}

		return $position;
	}

	public function set_due_date( int $task_id, string $date ): bool {
		// ToDo: Complete this method
	}

	public function mark_task_complete( int $task_id ): bool {
		// ToDo: Complete this method
	}

	/** ================================
	 *   Helper Methods
	 *  ================================ */

	/**
	 * Get parsed field value based on type and slug.
	 *
	 * @param TaskFieldValue $field_value Raw field value from the DB.
	 * @param TaskField $field Type of the field (e.g., 'number', 'text', etc.).
	 *
	 * @return mixed Parsed field value.
	 */
	public function get_field_value( TaskFieldValue $field_value, TaskField $field ) {
		if ( is_null( $field_value->value ) ) {
			return null;
		}

		$raw      = $field_value->value;
		$resolved = null;

		switch ( $field->type ) {
			case TaskFieldTypes::SELECT:
				$raw = (float) $raw;

				switch ( $field->slug ) {
					case 'status':
					case 'priority':
						$field_option = $this->task_field_option_repository->find( (int) $raw );
						if ( $field_option ) {
							$resolved = $field_option->to_array();
						}
						break;
				}
				break;

			case TaskFieldTypes::NUMBER:
				$raw = is_numeric( $raw ) ? (float) $raw : null;

				// Handle special cases based on slug.
				switch ( $field->slug ) {
					case 'creator':
						$user = get_userdata( (int) $raw );
						if ( $user ) {
							$resolved = array(
								'id'         => $user->ID,
								'username'   => $user->user_login,
								'email'      => $user->user_email,
								'first_name' => $user->first_name,
								'last_name'  => $user->last_name,
							);
						}
						break;

					case 'order':
						$order = wc_get_order( (int) $raw );
						if ( $order ) {
							$resolved = array(
								'id'           => $order->get_id(),
								'status'       => $order->get_status(),
								'total'        => $order->get_total(),
								'currency'     => $order->get_currency(),
								'date_created' => $order->get_date_created()
									? $order->get_date_created()->date( 'c' )
									: null,
							);
						}
						break;
				}
				break;

			case TaskFieldTypes::DATE:
				$timestamp = strtotime( $raw ) ?: null;
				if ( $timestamp ) {
					$resolved = date( 'c', $timestamp );
				}
				break;

			case TaskFieldTypes::TEXT:
			case TaskFieldTypes::SELECT:
			default:
				// For text and select, raw is already the useful value.
				// Resolved remains null.
				break;
		}

		$value = array(
			'raw'      => $raw,
			'resolved' => $resolved,
		);

		/**
		 * Filters the parsed field value.
		 *
		 * @param array|null     $value       Structured value with raw and resolved.
		 * @param TaskFieldValue $field_value Original field value object.
		 * @param TaskField      $field       Field definition object.
		 */
		return apply_filters( 'wpo_aom_task_get_field_value', $value, $field_value, $field );
	}
}
