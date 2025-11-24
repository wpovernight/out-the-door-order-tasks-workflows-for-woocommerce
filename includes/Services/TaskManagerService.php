<?php

namespace WPO\AOM\Services;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use WPO\AOM\Enums\DefaultTaskFields;
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

		$task_fields = $this->get_task_fields_and_values( $task_id );

		return array_merge( $task->to_array(), array( 'fields' => $task_fields ) );
	}

	/**
	 * Get all fields and their values for a specific task.
	 *
	 * @param int $task_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_task_fields_and_values( int $task_id ): array {
		$fields             = $this->get_all_fields();
		$values             = $this->get_field_values_for_task( $task_id );
		$values_by_field_id = array_column( $values, null, 'field_id' );
		$task_fields        = array();

		// Map field values to their respective fields.
		foreach ( $fields as $field ) {
			if ( ! isset( $values_by_field_id[ $field->id ] ) ) {
				$task_fields[] = array_merge( $field->to_array(), array( 'values' => null ) );
				continue;
			}

			$task_field_values = array();
			if ( is_array( $values_by_field_id[ $field->id ] ) ) {
				foreach ( $values_by_field_id[ $field->id ] as $single_value ) {
					$task_field_values[] = $this->get_field_value( $single_value, $field );
				}
			} else {
				$task_field_values[] = $this->get_field_value( $values_by_field_id[ $field->id ], $field );
			}

			$task_fields[] = array_merge( $field->to_array(), array( 'values' => $task_field_values ) );
		}

		return $task_fields;
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
	 * @param string $title
	 * @param string $description
	 * @param array|null $field_values
	 *
	 * @return array<string, mixed>|null
	 *
	 * @throws Exception
	 */
	public function create_task( string $title, string $description, ?array $field_values = array() ): ?array {
		if ( empty( $title ) ) {
			throw new Exception( 'Task title is required and must be a string.' );
		}

		$task_data = array(
			'title'       => sanitize_text_field( $title ),
			'description' => sanitize_textarea_field( $description )
		);
		$task      = new Task( $task_data );

		$result   = $this->task_repository->save( $task );
		$task->id = $result;

		if ( ! $result ) {
			throw new Exception( 'Failed to create task.' );
		}

		$status_id = 1; // Default status ID
		$field_values_array = array();

		foreach ( $field_values as $field_value ) {
			$field_id                        = $field_value['field_id'];
			$value                           = $field_value['value'];
			$field_values_array[ $field_id ] = $value;

			if ( $field_value['field_slug'] === 'status' ) {
				$status_id       = $value;
			}
		}

		// Set the position field manually to be last in the status column.
		$last_position = $this->task_repository->get_last_task_position( null, $status_id );

		$field_values_array[ DefaultTaskFields::POSITION ] = $last_position ? $last_position + 1.0 : 1.0;

		// Set field values.
		$this->task_field_value_repository->update_task_multiple_field_values( $task->id, $field_values_array );

		$task_with_data = $this->get_task_fields_and_values( $task->id );

		return array_merge( $task->to_array(), array( 'fields' => $task_with_data ) );
	}

	/**
	 * Update a Task by ID.
	 *
	 * @param int $task_id
	 * @param array<string, mixed> $task_data
	 * @param array|null $field_values
	 *
	 * @return array|null
	 * @throws Exception
	 */
	public function update_task( int $task_id, array $task_data, ?array $field_values = array() ): ?array {
		$task = $this->task_repository->find( $task_id );
		if ( ! $task ) {
			return false;
		}

		$task->fill( $task_data );

		$result = $this->task_repository->save( $task );

		if ( ! $result ) {
			throw new RuntimeException( 'Failed to update task.' );
		}

		// Update field values if provided.
		if ( ! empty( $field_values ) ) {
			$field_values_array = array();
			foreach ( $field_values as $field_value ) {
				$field_id                        = $field_value['field_id'];
				$value                           = $field_value['value'];
				$field_values_array[ $field_id ] = $value;

				// Check if status field is being updated
				if ( $field_value['field_slug'] === 'status' ) {
					$current_status_value = $this
						->task_field_value_repository
						->find_by_task_and_field( $task_id, DefaultTaskFields::STATUS );
					$new_status_value = $value;
				}
			}

			$this->task_field_value_repository->update_task_multiple_field_values( $task_id, $field_values_array );
		}

		// Update position if the status has been changed.
		if ( isset( $current_status_value ) && $current_status_value->value !== $new_status_value ) {
			$last_position = $this->task_repository->get_last_task_position(
				$task_id,
				(int) $field_values_array[ DefaultTaskFields::STATUS ],
			);

			$field_values_array[ DefaultTaskFields::POSITION ] = $last_position ? $last_position + 1.0 : 1.0;

			$this->task_field_value_repository->update_task_multiple_field_values( $task_id, $field_values_array );
		}

		$task_with_data = $this->get_task_fields_and_values( $task_id );

		return array_merge( $task->to_array(), array( 'fields' => $task_with_data ) );
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
	public function get_field_option( int $option_id ): ?TaskFieldOption {
		return $this->task_field_option_repository->find( $option_id );
	}

	/**
	 * Get all options for a specific select-type field, ordered by position.
	 *
	 * @param int $field_id
	 *
	 * @return array
	 */
	public function get_field_options_by_field_id( int $field_id ): array {
		return $this->task_field_option_repository->get_by_field_id_ordered( $field_id );
	}

	/**
	 * Get all options for a specific select-type field by the field slug.
	 *
	 * @param string $slug
	 *
	 * @return array
	 */
	public function get_field_options_by_field_slug( string $slug ): array {
		$field = $this->task_field_repository->find_by_slug( $slug );
		if ( ! $field ) {
			return array();
		}

		return $this->get_field_options_by_field_id( $field->id );
	}

	/**
	 * Add an option to a select-type field.
	 *
	 * @param int $field_id
	 * @param array $option_data
	 *
	 * @return int
	 */
	public function add_field_option( int $field_id, array $option_data ): int {
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
	public function update_field_option( int $option_id, array $option_data ): bool {
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
	public function delete_field_option( int $option_id ): bool {
		return $this->task_field_option_repository->delete( $option_id );
	}

	/**
	 * Update the position order of field options.
	 *
	 * @param int   $field_id           The field ID.
	 * @param array $ordered_option_ids Array of option IDs in desired order.
	 *
	 * @return void
	 * @throws InvalidArgumentException If field doesn't exist or option IDs are invalid.
	 */
	public function update_field_option_positions( int $field_id, array $ordered_option_ids ): void {
		// Validate that the field exists.
		$field = $this->task_field_repository->find( $field_id );
		if ( ! $field ) {
			throw new InvalidArgumentException( "Field with ID {$field_id} does not exist." );
		}

		// Get all existing options for this field.
		$existing_options = $this->task_field_option_repository->get_by_field_id_ordered( $field_id );
		if ( empty( $existing_options ) ) {
			throw new InvalidArgumentException( "Field {$field_id} has no options to reorder." );
		}

		// Extract valid option IDs for this field.
		$valid_option_ids = array_map(
			function ( $option ) {
				return $option->id;
			},
			$existing_options
		);

		// Validate that all provided option IDs belong to this field.
		foreach ( $ordered_option_ids as $option_id ) {
			if ( ! in_array( $option_id, $valid_option_ids, true ) ) {
				throw new InvalidArgumentException( "Option ID {$option_id} does not belong to field {$field_id}." );
			}
		}

		// Validate no duplicates in the provided list.
		$unique_ids = array_unique( $ordered_option_ids );
		if ( count( $unique_ids ) !== count( $ordered_option_ids ) ) {
			throw new InvalidArgumentException(
				'Duplicate option IDs detected in the reorder request. Each option ID must appear exactly once.'
			);
		}

		// Validate completeness: All option IDs must be provided.
		if ( count( $ordered_option_ids ) !== count( $existing_options ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Incomplete option list provided. Expected %d option IDs, got %d. All options must be included when reordering.',
					count( $existing_options ),
					count( $ordered_option_ids )
				)
			);
		}

		// Update positions in the database.
		$this->task_field_option_repository->update_positions( $field_id, $ordered_option_ids );

		// Clear repository cache to ensure fresh data on next fetch.
		$this->task_field_option_repository::clear_cache();
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
		// Check if the new status ID is valid.
		$target_status_option_field = $this->get_field_option( $target_status_id );
		if ( ! $target_status_option_field || $target_status_option_field->field_id !== DefaultTaskFields::STATUS ) {
			return false;
		}

		// If no previous task is specified, place at the start.
		$previous_position = 0.0;

		// Determine new position.
		if ( ! empty( $previous_task_id ) ) {
			$previous_task_position_value = $this
				->task_field_value_repository
				->find_by_task_and_field( $previous_task_id, DefaultTaskFields::POSITION );
			$previous_position = (float) ( $previous_task_position_value->value ?? 0.0 );
		}

		$next_position_value = $this->task_repository->get_next_task_position(
			$previous_task_id,
			$target_status_id,
			$previous_position,
			$task_id
		);
		$new_position        = $next_position_value
			? $this->calculate_fractional_position( $previous_position, $next_position_value, $target_status_id )
			: $previous_position + 1.0;

		$new_position = apply_filters(
			'wpo_aom_task_calculated_new_position',
			(float) number_format( $new_position, 5, '.', '' ),
			$task_id,
			$previous_task_id,
			$target_status_id
		);

		$update_data = array(
			DefaultTaskFields::STATUS => $target_status_id,
			DefaultTaskFields::POSITION => $new_position,
		);

		$this->task_field_value_repository->update_task_multiple_field_values( $task_id, $update_data );

		return $new_position;
	}

	/**
	 * Calculate a new fractional position between two positions.
	 *
	 * @param float $previous_position
	 * @param float $next_position
	 * @param int $status_id
	 *
	 * @return float
	 */
	private function calculate_fractional_position( float $previous_position, float $next_position, int $status_id ): float {
		if ( $previous_position >= $next_position ) {
			throw new InvalidArgumentException( 'Previous position must be less than next position.' );
		}

		// Handle case where putting at the start, while the next position is greater than 1.0.
		if ( 0.0 === $previous_position && $next_position > 1.0  ) {
			return 1.0;
		}

		$position = ( $previous_position + $next_position ) / 2;

		$precision = 0.0001;
		// Check for precision issues.
		if ( abs( $next_position - $previous_position ) < $precision ) {
			// ToDo: Improve this part by considering async rebalancing, locks, etc.
			$this->task_repository->rebalance_positions( $status_id );
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
			default:
				// For text, raw is already the useful value.
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
