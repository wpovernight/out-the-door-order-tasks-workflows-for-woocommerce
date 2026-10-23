<?php

namespace WPO\OTD\Services;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Enums\TaskFieldTypes;
use WPO\OTD\Exceptions\TaskCreatedWithErrorException;
use WPO\OTD\Models\Task;
use WPO\OTD\Models\TaskField;
use WPO\OTD\Models\TaskFieldOption;
use WPO\OTD\Models\TaskFieldValue;
use WPO\OTD\Repositories\TaskFieldOptionRepository;
use WPO\OTD\Repositories\TaskFieldRepository;
use WPO\OTD\Repositories\TaskFieldValueRepository;
use WPO\OTD\Core\Logger;
use WPO\OTD\Repositories\TaskRepository;

defined( 'ABSPATH' ) || exit;

final class TaskManagerService {
	protected readonly TaskRepository $task_repository;
	protected readonly TaskFieldRepository $task_field_repository;
	protected readonly TaskFieldOptionRepository $task_field_option_repository;
	protected readonly TaskFieldValueRepository $task_field_value_repository;
	protected readonly TaskStatusRoleService $task_status_role_service;

	/**
	 * Constructor.
	 */
	public function __construct(
		TaskRepository $task_repository,
		TaskFieldRepository $task_field_repository,
		TaskFieldOptionRepository $task_field_option_repository,
		TaskFieldValueRepository $task_field_value_repository,
		TaskStatusRoleService $task_status_role_service
	) {
		$this->task_repository              = $task_repository;
		$this->task_field_repository        = $task_field_repository;
		$this->task_field_option_repository = $task_field_option_repository;
		$this->task_field_value_repository  = $task_field_value_repository;
		$this->task_status_role_service     = $task_status_role_service;
	}

	/**
	 * Register hooks and filters.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Rebalance task positions hook.
		add_action( 'wpo_otd_rebalance_task_positions', array( $this, 'rebalance_task_positions' ) );
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
				$task_fields[] = array_merge( $field->to_array(), array( 'values' => $field_value ?: null ) );
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
		$fields      = $this->get_all_fields();
		$values      = $this->get_field_values_for_task( $task_id );
		$task_fields = array();

		// Map field values to their respective fields.
		foreach ( $fields as $field ) {
			if ( ! isset( $values[ $field->id ] ) ) {
				$task_fields[] = array_merge( $field->to_array(), array( 'values' => null ) );
				continue;
			}

			$task_field_values = array();
			if ( is_array( $values[ $field->id ] ) ) {
				foreach ( $values[ $field->id ] as $single_value ) {
					$task_field_values[] = $this->get_field_value( $single_value, $field );
				}
			} else {
				$task_field_values[] = $this->get_field_value( $values[ $field->id ], $field );
			}

			$task_fields[] = array_merge( $field->to_array(), array( 'values' => $task_field_values ) );
		}

		return $task_fields;
	}

	/**
	 * A sanitized title or description as the tasks table holds it, so a long one is cut rather than failing the
	 * save. When the cut splits an entity, such as leaving the "&l" of "&lt;", that part is dropped too, so the text
	 * does not end in a broken one.
	 *
	 * @param string $column title | description.
	 * @param string $text   Sanitized.
	 *
	 * @return string
	 */
	private function fit_to_column( string $column, string $text ): string {
		$fitted = $this->task_repository->fit_to_column( $column, $text );

		if ( $fitted === $text || ! preg_match( '/&[#a-zA-Z0-9]*$/', $fitted, $tail ) ) {
			return $fitted;
		}

		// Fitting never drops an "&" it does not cut off, so the last one kept is the text's Nth "&", N being how many
		// were kept. The kept part ends in a cut entity only when the text holds a longer whole entity from there;
		// otherwise, as for "Q&A" kept from "Q&A😀", the tail is the text's own.
		$offset = -1;
		for ( $count = substr_count( $fitted, '&' ); $count > 0; $count-- ) {
			$offset = strpos( $text, '&', $offset + 1 );
		}

		if (
			preg_match( '/^&(?:[a-zA-Z][a-zA-Z0-9]{0,31}|#[0-9]+|#[xX][0-9a-fA-F]+);/', substr( $text, $offset ), $entity )
			&& strlen( $entity[0] ) > strlen( $tail[0] )
		) {
			$fitted = substr( $fitted, 0, -strlen( $tail[0] ) );
		}

		return $fitted;
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
	 * The title and description are sanitized, then cut to what their columns hold.
	 *
	 * When the task's field values cannot be worked out or saved, the task is removed again and the failure is
	 * rethrown. Once they are saved, the task is kept: a failure while reading it back or in a wpo_otd_task_created
	 * listener is rethrown as TaskCreatedWithErrorException, which carries the task's ID, so a caller does not create
	 * it a second time.
	 *
	 * @param string $title
	 * @param string $description
	 * @param array|null $field_values
	 *
	 * @return array<string, mixed>|null
	 *
	 * @throws TaskCreatedWithErrorException When the task was created, but reading it back or announcing it failed.
	 * @throws Exception|\Throwable When the task could not be created.
	 */
	public function create_task( string $title, string $description, ?array $field_values = array() ): ?array {
		// Checked once sanitized and fitted, so a title of markup, whitespace or characters the table cannot store
		// alone is not saved empty.
		$title = $this->fit_to_column( 'title', sanitize_text_field( $title ) );

		if ( '' === $title ) {
			throw new Exception( 'Task title is required and must be a string.' );
		}

		$status_id          = null;
		$field_values_array = array();

		foreach ( $field_values ?? array() as $field_value ) {
			$field_id                        = (int) $field_value['field_id'];
			$value                           = $field_value['value'];
			$field_values_array[ $field_id ] = $value;

			if ( DefaultTaskFields::STATUS === $field_id && ! empty( $value ) ) {
				$status_id = (int) $value;
			}
		}

		// Every task sits in a status column: without one, it starts in the first.
		if ( null === $status_id ) {
			$first_status = $this->get_field_options_by_field_id( DefaultTaskFields::STATUS )[0] ?? null;

			if ( ! $first_status ) {
				throw new RuntimeException( 'There is no status to place the task in.' );
			}

			$status_id                                       = $first_status->id;
			$field_values_array[ DefaultTaskFields::STATUS ] = $status_id;
		}

		$task_data = array(
			'title'       => $title,
			'description' => $this->fit_to_column( 'description', sanitize_textarea_field( $description ) ),
		);
		$task      = new Task( $task_data );

		$result = $this->task_repository->save( $task );

		if ( ! $result ) {
			$db_error = $this->task_repository->get_last_db_error();
			throw new Exception(
				'Failed to create task.' . ( $db_error ? ' Database error: ' . esc_html( $db_error ) : '' )
			);
		}

		$task->id = $result;

		// Until its field values are saved, the task has no status column to sit in, so a failure removes it again;
		// nothing has announced it yet.
		try {
			// Set the position field manually to be last in the status column.
			$last_position = $this->task_repository->get_last_task_position( null, $status_id );

			$field_values_array[ DefaultTaskFields::POSITION ] = $last_position ? $last_position + 1.0 : 1.0;

			$done_option_id = $this->task_status_role_service->get_done_field_option_id();

			// Auto-set "done_date" if creating with the option assigned to the "done" role.
			$status_option = $this->get_field_option( $status_id );
			if (
				$status_option &&
				$status_option->id === $done_option_id &&
				! isset( $field_values_array[ DefaultTaskFields::DONE_DATE ] )
			) {
				$field_values_array[ DefaultTaskFields::DONE_DATE ] = gmdate( DefaultTaskFields::DONE_DATE_FORMAT );
			}

			$this->save_field_values( $task->id, $field_values_array );
		} catch ( \Throwable $exception ) {
			if ( ! $this->task_repository->delete( $task->id ) ) {
				Logger::error( "Task {$task->id} was created without its field values and could not be removed: " . $this->task_repository->get_last_db_error() );
			}

			throw $exception;
		}

		try {
			$fields_and_values = $this->get_task_fields_and_values( $task->id );

			$task_with_fields = array_merge( $task->to_array(), array( 'fields' => $fields_and_values ) );

			/**
			 * Fires after a task has been created.
			 *
			 * @param int   $task_id          The ID of the created task.
			 * @param array $task_with_fields Complete task data with fields and values.
			 * @param array $field_values     Field values that were set on creation.
			 */
			do_action( 'wpo_otd_task_created', $task->id, $task_with_fields, $field_values_array );
		} catch ( \Throwable $exception ) {
			throw new TaskCreatedWithErrorException( $task->id, $exception );
		}

		return $task_with_fields;
	}

	/**
	 * Update a Task by ID.
	 *
	 * The task's own columns are saved before its field values. When the field values then fail, those columns stay
	 * changed: the exception means the update was partly saved, and wpo_otd_task_updated does not fire.
	 *
	 * Each field value names its field by 'field_id'. A 'field_slug' sent with it is ignored: the change is reported
	 * under the slug of the field it is saved to. A value for a field that does not exist is skipped. A field sent
	 * more than once saves its last value, and only that value is compared with the stored one.
	 *
	 * A title or description sent is sanitized, then cut to what its column holds.
	 *
	 * @param int $task_id
	 * @param array<string, mixed> $task_data
	 * @param array|null $field_values Entries of 'field_id' and 'value'.
	 *
	 * @return array|null
	 * @throws InvalidArgumentException If the task does not exist.
	 * @throws RuntimeException If the persistence call fails.
	 * @throws Exception If a title is sent that is empty once sanitized and fitted, or the field values fail to save.
	 * @throws \Throwable
	 */
	public function update_task( int $task_id, array $task_data, ?array $field_values = array() ): ?array {
		$task = $this->task_repository->find( $task_id );
		if ( ! $task ) {
			throw new InvalidArgumentException( 'Task not found.' );
		}

		// Only the title and description can change here. Callers such as the REST API pass their
		// whole payload, which must not reach other properties (the ID, the timestamps).
		$task_data = array_intersect_key( $task_data, array_flip( array( 'title', 'description' ) ) );

		// Sanitize input before filling the model.
		if ( array_key_exists( 'title', $task_data ) ) {
			$task_data['title'] = $this->fit_to_column( 'title', sanitize_text_field( (string) $task_data['title'] ) );

			if ( '' === $task_data['title'] ) {
				throw new Exception( 'Task title is required and must be a string.' );
			}
		}
		if ( array_key_exists( 'description', $task_data ) ) {
			$task_data['description'] = $this->fit_to_column( 'description', sanitize_textarea_field( (string) $task_data['description'] ) );
		}

		// Track which fields were actually updated with old and new values.
		$updated_fields     = array();
		$field_values_array = array();

		// Check for task data changes (title, description).
		$original_task_data = $task->to_array();
		foreach ( $task_data as $key => $new_value ) {
			if ( isset( $original_task_data[ $key ] ) && $original_task_data[ $key ] !== $new_value ) {
				$updated_fields[ $key ] = array(
					'old_value' => $original_task_data[ $key ],
					'new_value' => $new_value,
				);
			}
		}

		$task->fill( $task_data );

		$result = $this->task_repository->save( $task );

		if ( false === $result ) {
			$db_error = $this->task_repository->get_last_db_error();
			throw new RuntimeException(
				'Failed to update task.' . ( $db_error ? ' Database error: ' . esc_html( $db_error ) : '' )
			);
		}

		// Update field values if provided.
		if ( ! empty( $field_values ) ) {
			// Fetch all current field values and fields at once to avoid N+1 queries.
			$current_field_values = $this->get_field_values_for_task( $task_id );
			$fields               = $this->get_all_fields( 'id' );

			foreach ( $field_values as $field_value ) {
				if ( ! isset( $field_value['field_id'], $field_value['value'] ) ) {
					continue;
				}

				$field_id = (int) $field_value['field_id'];
				if ( ! isset( $fields[ $field_id ] ) ) {
					continue;
				}

				$value                           = $field_value['value'];
				$field_slug                      = $fields[ $field_id ]->slug;
				$field_values_array[ $field_id ] = $value;

				$current_value = null;
				if ( isset( $current_field_values[ $field_id ] ) ) {
					$current_values_objects = $current_field_values[ $field_id ];
					// Extract the actual values from TaskFieldValue objects.
					if ( is_array( $current_values_objects ) ) {
						$current_value = array_map(
							function ( $obj ) {
								return $obj->value;
							},
							$current_values_objects
						);

						if ( count( $current_value ) === 1 ) {
							$current_value = $current_value[0];
						}
					}
				}

				// Normalize both values to arrays of strings for consistent comparison: stored values
				// come back from the database as strings, so 6 and "6" are the same value.
				$current_normalized = array_map( array( $this, 'normalize_for_comparison' ), (array) $current_value );
				$new_normalized     = array_map( array( $this, 'normalize_for_comparison' ), (array) $value );

				// Sort arrays to handle order differences.
				sort( $current_normalized, SORT_STRING );
				sort( $new_normalized, SORT_STRING );

				// Check if the field value actually changed.
				$has_changed = $current_normalized !== $new_normalized;

				if ( $has_changed ) {
					$updated_fields[ $field_slug ] = array(
						'old_value' => $current_value,
						'new_value' => $value,
					);
				} else {
					// An earlier entry for the same field may have reported a change this value undoes.
					unset( $updated_fields[ $field_slug ] );
				}

				// Check if status field is being updated to handle position update later.
				if ( DefaultTaskFields::STATUS === $field_id ) {
					$current_status_value = $this
						->task_field_value_repository
						->find_by_task_and_field( $task_id, DefaultTaskFields::STATUS );
					$new_status_value = $value;
				}
			}

			// Update position and done_date if the status has been changed. Worked out before the write, so they
			// are saved with the status in one write, or not at all. The task's own position is left out of the
			// column it moves to, so reading it before the status is saved gives the same result.
			// Note: $field_values_array[STATUS] is guaranteed to exist here because $current_status_value
			// is only set when the status field is present in $field_values (above loop).
			if ( isset( $current_status_value ) && (string) $current_status_value->value !== (string) $new_status_value ) {
				$last_position = $this->task_repository->get_last_task_position(
					$task_id,
					(int) $field_values_array[ DefaultTaskFields::STATUS ],
				);

				$field_values_array[ DefaultTaskFields::POSITION ] = $last_position ? $last_position + 1.0 : 1.0;

				$done_option_id = $this->task_status_role_service->get_done_field_option_id();

				// Auto-set "done_date" when moving into the option assigned to the "done" role.
				$new_status_option = $this->get_field_option( (int) $new_status_value );
				if (
					$new_status_option &&
					$new_status_option->id === $done_option_id &&
					! isset( $field_values_array[ DefaultTaskFields::DONE_DATE ] )
				) {
					$field_values_array[ DefaultTaskFields::DONE_DATE ] = gmdate( DefaultTaskFields::DONE_DATE_FORMAT );
				} elseif (
					$new_status_option &&
					$new_status_option->id !== $done_option_id &&
					! isset( $field_values_array[ DefaultTaskFields::DONE_DATE ] )
				) {
					$field_values_array[ DefaultTaskFields::DONE_DATE ] = null;
				}
			}

			$this->save_field_values( $task_id, $field_values_array );
		}

		$fields_and_values = $this->get_task_fields_and_values( $task_id );

		$task_with_fields = array_merge( $task->to_array(), array( 'fields' => $fields_and_values ) );

		$this->announce_task_update( $task_id, $task_with_fields, $updated_fields );

		return $task_with_fields;
	}

	/**
	 * Delete a Task by ID.
	 *
	 * @param int $task_id
	 *
	 * @return int|false
	 * @throws RuntimeException
	 * @throws InvalidArgumentException
	 */
	public function delete_task( int $task_id ): int|false {
		$result = $this->task_repository->delete( $task_id );

		if ( $result ) {
			/**
			 * Fires after a task has been deleted.
			 *
			 * @param int       $task_id The ID of the deleted task.
			 */
			do_action( 'wpo_otd_task_deleted', $task_id );
		}

		return $result;
	}

	/** ================================
	 *   Task Field Methods
	 *  ================================ */

	/**
	 * Create a new Task Field.
	 *
	 * @param array $data
	 *
	 * @return int|false
	 */
	public function create_field( array $data ): int|false {
		$field = new TaskField( $data );

		$result = $this->task_field_repository->save( $field );

		if ( $result ) {
			$field->id = $result;

			/**
			 * Fires after a task field has been created.
			 *
			 * @param int   $field_id The ID of the created field.
			 * @param array $data     The field data.
			 */
			do_action( 'wpo_otd_field_created', $field->id, $data );
		} else {
			$db_error = $this->task_field_repository->get_last_db_error();
			Logger::error(
				'Failed to create task field.' . ( $db_error ? ' Database error: ' . $db_error : '' )
			);
		}

		return $result;
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

		$result = false !== $this->task_field_repository->save( $field );

		if ( $result ) {
			/**
			 * Fires after a task field has been updated.
			 *
			 * @param int   $field_id The ID of the updated field.
			 * @param array $data     The updated field data.
			 */
			do_action( 'wpo_otd_field_updated', $field_id, $data );
		} else {
			$db_error = $this->task_field_repository->get_last_db_error();
			Logger::error(
				sprintf(
					'Failed to update task field %d.%s',
					$field_id,
					$db_error ? ' Database error: ' . $db_error : ''
				)
			);
		}

		return $result;
	}

	/**
	 * Delete a Task Field by ID.
	 *
	 * @param int $field_id
	 *
	 * @return bool
	 */
	public function delete_field( int $field_id ): bool {
		$result = $this->task_field_repository->delete( $field_id );

		if ( $result ) {
			// The field and, through the foreign key, its options are gone: drop any cached copies.
			$this->task_field_repository::clear_cache();
			$this->task_field_option_repository::clear_cache();

			/**
			 * Fires after a task field has been deleted.
			 *
			 * @param int $field_id The ID of the deleted field.
			 */
			do_action( 'wpo_otd_field_deleted', $field_id );
		}

		return $result;
	}

	/**
	 * Get all Task Fields.
	 *
	 * @param string $index_by
	 *
	 * @return array<int, TaskField>
	 */
	public function get_all_fields( string $index_by = 'slug' ): array {
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
	 * @param int   $field_id
	 * @param array $option_data
	 *
	 * @return array
	 * @throws \Throwable Propagated from the transactional shift+insert if the DB layer fails.
	 */
	public function add_field_option( int $field_id, array $option_data ): array {
		$option_data['field_id'] = $field_id;

		// Auto-generate slug from label if not provided.
		if ( empty( $option_data['slug'] ) ) {
			$option_data['slug'] = sanitize_title( $option_data['label'] );
		}

		// Enforce slug uniqueness within the field.
		$slug_exist = $this
			->task_field_option_repository
			->where( 'field_id', $field_id )
			->where( 'slug', $option_data['slug'] )
			->first();

		if ( null !== $slug_exist ) {
			// Slug collided: append -2, -3, ... until free.
			$base_slug = $option_data['slug'];
			$suffix    = 2;

			do {
				$candidate = $base_slug . '-' . $suffix;
				++$suffix;
			} while (
				null !== $this
					->task_field_option_repository
					->where( 'field_id', $field_id )
					->where( 'slug', $candidate )
					->first()
			);

			$option_data['slug'] = $candidate;
		}

		// Auto-assign a random color if not provided.
		if ( empty( $option_data['color'] ) ) {
			$option_data['color'] = sprintf(
				'#%06X',
				wp_rand( 0, 0xFFFFFF )
			);
		}

		// Resolve the target position against the current max for this field. Positions are 1-based;
		// any missing / out-of-range value falls through to "append at the end" so we never insert
		// negative positions or leave gaps from oversized inputs.
		$last_option        = $this
			->task_field_option_repository
			->where( 'field_id', $field_id )
			->order_by( 'position', 'DESC' )
			->first();
		$max_position       = $last_option ? (int) $last_option->position : 0;
		$requested_position = isset( $option_data['position'] ) ? (int) $option_data['position'] : 0;
		$shift_needed       = $requested_position >= 1 && $requested_position <= $max_position;

		$option_data['position'] = $shift_needed ? $requested_position : $max_position + 1;

		// Shift + insert must be atomic; otherwise a failed insert leaves every subsequent option
		// shifted up by one with no row filling the gap.
		$option_id = $this->task_field_option_repository->transaction(
			function ( $repository ) use ( $field_id, $shift_needed, $option_data ) {
				if ( $shift_needed ) {
					$repository->increment_positions_from( $field_id, $option_data['position'] );
				}
				return $repository->insert( $option_data );
			}
		);

		if ( ! $option_id ) {
			$db_error = $this->task_field_option_repository->get_last_db_error();
			throw new RuntimeException(
				'Failed to create field option.' . ( $db_error ? ' Database error: ' . esc_html( $db_error ) : '' )
			);
		}

		// Drop any cached reads.
		$this->task_field_option_repository::clear_cache();

		$option_data['id'] = (int) $option_id;

		/**
		 * Fires after a field option has been created.
		 *
		 * @param int   $option_id   The ID of the created option.
		 * @param int   $field_id    The ID of the field the option belongs to.
		 * @param array $option_data The option data.
		 */
		do_action( 'wpo_otd_field_option_created', $option_id, $field_id, $option_data );

		return $option_data;
	}

	/**
	 * Update an option by ID.
	 *
	 * @param int   $field_id
	 * @param int   $option_id
	 * @param array $option_data
	 *
	 * @return array
	 * @throws InvalidArgumentException If the option doesn't exist or doesn't belong to the given field.
	 * @throws RuntimeException If the persistence call fails.
	 */
	public function update_field_option( int $field_id, int $option_id, array $option_data ): array {
		$option = $this->task_field_option_repository->find( $option_id );
		if ( ! $option ) {
			throw new InvalidArgumentException( 'Field option not found.' );
		}

		if ( $option->field_id !== $field_id ) {
			throw new InvalidArgumentException( 'Field option does not belong to the given field.' );
		}

		// Enforce slug uniqueness within the field, skipping when the slug isn't changing.
		if ( ! empty( $option_data['slug'] ) && $option_data['slug'] !== $option->slug ) {
			$slug_exist = $this
				->task_field_option_repository
				->where( 'field_id', $option->field_id )
				->where( 'slug', $option_data['slug'] )
				->first();

			if ( null !== $slug_exist ) {
				// Slug collided: append -2, -3, ... until free. The `id != $option_id` guard
				// protects against a candidate (e.g. "foo-2") matching the row's own current slug.
				$base_slug = $option_data['slug'];
				$suffix    = 2;

				do {
					$candidate = $base_slug . '-' . $suffix;
					++$suffix;
				} while (
					null !== $this->task_field_option_repository
						->where( 'field_id', $option->field_id )
						->where( 'slug', $candidate )
						->where( 'id', '!=', $option_id )
						->first()
				);

				$option_data['slug'] = $candidate;
			}
		}

		$option->fill( $option_data );

		if ( false === $this->task_field_option_repository->save( $option ) ) {
			$db_error = $this->task_field_option_repository->get_last_db_error();
			throw new RuntimeException(
				'Failed to persist field option.' . ( $db_error ? ' Database error: ' . esc_html( $db_error ) : '' )
			);
		}

		$this->task_field_option_repository::clear_cache();

		/**
		 * Fires after a field option has been updated.
		 *
		 * @param int   $option_id   The ID of the updated option.
		 * @param array $option_data The updated option data.
		 */
		do_action( 'wpo_otd_field_option_updated', $option_id, $option_data );

		return $option->to_array();
	}

	/**
	 * Delete an option by ID.
	 *
	 * @param int $field_id
	 * @param int $option_id
	 *
	 * @return bool
	 * @throws InvalidArgumentException If the option doesn't exist or doesn't belong to the given field.
	 * @throws RuntimeException If the option is assigned to a status role, or if any
	 *                          task currently references the option as a field value.
	 */
	public function delete_field_option( int $field_id, int $option_id ): bool {
		$option = $this->task_field_option_repository->find( $option_id );
		if ( ! $option ) {
			throw new InvalidArgumentException( 'Field option not found.' );
		}

		if ( $option->field_id !== $field_id ) {
			throw new InvalidArgumentException( 'Field option does not belong to the given field.' );
		}

		if ( $this->task_status_role_service->is_field_option_assigned_to_any_role( $option_id ) ) {
			throw new RuntimeException( 'This option is assigned to a status role and cannot be deleted.' );
		}

		// Block deletion when any task currently references this option as a field value.
		// The frontend handles the resolution path (move tasks to another option, or delete
		// the tasks first, then retry the option delete) so the API stays focused.
		$tasks_using_option = $this->task_field_value_repository
			->where( 'field_id', $option->field_id )
			->where( 'value', (string) $option_id )
			->get();

		if ( ! empty( $tasks_using_option ) ) {
			throw new RuntimeException(
				sprintf(
					'%d task(s) are using this option. Move them to a different option before deleting.',
					count( $tasks_using_option )
				)
			);
		}

		$result = $this->task_field_option_repository->delete( $option_id );

		if ( $result ) {
			$this->task_field_option_repository::clear_cache();

			/**
			 * Fires after a field option has been deleted.
			 *
			 * @param int $option_id The ID of the deleted option.
			 */
			do_action( 'wpo_otd_field_option_deleted', $option_id );
		}

		return $result !== false;
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
			throw new InvalidArgumentException( esc_html( "Field with ID $field_id does not exist." ) );
		}

		// Get all existing options for this field.
		$existing_options = $this->task_field_option_repository->get_by_field_id_ordered( $field_id );
		if ( empty( $existing_options ) ) {
			throw new InvalidArgumentException( esc_html( "Field $field_id has no options to reorder." ) );
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
				throw new InvalidArgumentException( esc_html( "Option ID $option_id does not belong to field $field_id." ) );
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

		/**
		 * Filter the ordered option IDs before saving.
		 *
		 * @param array $ordered_option_ids The array of ordered option IDs.
		 * @param int   $field_id           The ID of the field the options belong to.
		 */
		$ordered_option_ids = apply_filters( 'wpo_otd_field_ordered_option_ids', $ordered_option_ids, $field_id );

		// Update positions in the database.
		$this->task_field_option_repository->update_positions( $field_id, $ordered_option_ids );

		// Clear repository cache to ensure fresh data on next fetch.
		$this->task_field_option_repository::clear_cache();
	}

	/** ================================
	 *   Task Field Value Methods
	 *  ================================ */

	/**
	 * Get field values for a specific task, grouped by field ID: each field holds the list of its stored values.
	 *
	 * @param int $task_id
	 *
	 * @return array<int, TaskFieldValue[]>
	 */
	public function get_field_values_for_task( int $task_id ): array {
		$values  = $this->task_field_value_repository->find_all_by( 'task_id', $task_id );
		$grouped = array();

		foreach ( $values as $value ) {
			if ( ! isset( $grouped[ $value->field_id ] ) ) {
				$grouped[ $value->field_id ] = array();
			}
			$grouped[ $value->field_id ][] = $value;
		}

		return $grouped;
	}

	/**
	 * Set or update a field value for a specific task.
	 *
	 * Fires wpo_otd_task_updated when the value is saved and differs from the stored one.
	 *
	 * @param int $task_id
	 * @param int $field_id
	 * @param mixed $value
	 *
	 * @return bool False when the value is not saved, which includes a field that does not exist.
	 */
	public function set_field_value( int $task_id, int $field_id, $value ): bool {
		$updated_fields = array();

		$saved = $this->write_field_value( $task_id, $this->task_field_repository->find( $field_id ), $value, $updated_fields );

		if ( $saved ) {
			$this->announce_field_value_changes( $task_id, $updated_fields );
		}

		return $saved;
	}

	/**
	 * Update multiple field values for a specific task.
	 *
	 * When any saved value differs from the stored one, fires wpo_otd_task_updated once, listing each such value. When
	 * some values fail, the others stay saved and are still listed. A value for a field that does not exist fails.
	 *
	 * @param int $task_id
	 * @param array $field_data
	 *
	 * @return bool False when any value is not saved.
	 */
	public function update_field_values( int $task_id, array $field_data ): bool {
		$success        = true;
		$updated_fields = array();
		$fields         = array_column(
			$this->task_field_repository->find_many( array_map( 'intval', array_keys( $field_data ) ) ),
			null,
			'id'
		);

		foreach ( $field_data as $field_id => $value ) {
			$result = $this->write_field_value( $task_id, $fields[ (int) $field_id ] ?? null, $value, $updated_fields );
			if ( ! $result ) {
				// write_field_value() logs a database failure; a field that does not exist fails without a log.
				$success = false;
			}
		}

		$this->announce_field_value_changes( $task_id, $updated_fields );

		return $success;
	}

	/**
	 * Save one field value of a task, without announcing it.
	 *
	 * When the value is saved and differs from the stored one, the change is added to $updated_fields under the
	 * field's slug, with 'old_value' as stored and 'new_value' as given. Without a field, nothing is saved.
	 *
	 * @param int                  $task_id
	 * @param TaskField|null       $field
	 * @param mixed                $value
	 * @param array<string, array> $updated_fields
	 *
	 * @return bool False when the value is not saved.
	 */
	private function write_field_value( int $task_id, ?TaskField $field, $value, array &$updated_fields ): bool {
		if ( ! $field ) {
			return false;
		}

		$field_id     = $field->id;
		$field_value  = $this->task_field_value_repository->find_by_task_and_field( $task_id, $field_id );
		$stored_value = $field_value ? $field_value->value : null;
		$new_stored   = maybe_serialize( $value );
		$new_stored   = null === $new_stored ? null : (string) $new_stored;

		if ( $field_value ) {
			$field_value->value = $new_stored;

			$saved = false !== $this->task_field_value_repository->save( $field_value );
		} else {
			$new_value = array(
				'task_id'  => $task_id,
				'field_id' => $field_id,
				'value'    => $new_stored,
			);

			$saved = false !== $this->task_field_value_repository->insert( $new_value );
		}

		if ( ! $saved ) {
			$db_error = $this->task_field_value_repository->get_last_db_error();
			Logger::error(
				sprintf(
					'Failed to persist field value for task %d, field %d.%s',
					$task_id,
					$field_id,
					$db_error ? ' Database error: ' . $db_error : ''
				)
			);

			return false;
		}

		if ( $stored_value !== $new_stored ) {
			// 'old_value' stays the stored string: create_task() and update_task() store a client's string as sent, so
			// unserializing it could build any object the client named.
			$updated_fields[ $field->slug ] = array(
				'old_value' => $stored_value,
				'new_value' => $value,
			);
		}

		return true;
	}

	/**
	 * Announce field values saved outside update_task(), when any of them changed.
	 *
	 * @param int                  $task_id
	 * @param array<string, array> $updated_fields
	 *
	 * @return void
	 */
	private function announce_field_value_changes( int $task_id, array $updated_fields ): void {
		if ( empty( $updated_fields ) ) {
			return;
		}

		$task_with_fields = $this->get_task_with_fields( $task_id );

		if ( null === $task_with_fields ) {
			return;
		}

		$this->announce_task_update( $task_id, $task_with_fields, $updated_fields );
	}

	/**
	 * Fire wpo_otd_task_updated.
	 *
	 * @param int                  $task_id
	 * @param array<string, mixed> $task_with_fields
	 * @param array<string, array> $updated_fields
	 *
	 * @return void
	 */
	private function announce_task_update( int $task_id, array $task_with_fields, array $updated_fields ): void {
		/**
		 * Fires after a task has been updated: by update_task(), even when nothing changed, and by
		 * set_field_value() and update_field_values() when they saved a value that differs from the stored one.
		 * Moving or creating a task does not fire it.
		 *
		 * @param int   $task_id          The ID of the updated task.
		 * @param array $task_with_fields Complete updated task data with fields and values.
		 * @param array $updated_fields   Associative array of field slugs that were changed,
		 *                                with 'old_value' and 'new_value' for each.
		 */
		do_action( 'wpo_otd_task_updated', $task_id, $task_with_fields, $updated_fields );
	}

	/** ================================
	 *   Additional Task Operations
	 *  ================================ */

	/**
	 * Move a task to a different position.
	 *
	 * @param int      $task_id             The ID of the task to move.
	 * @param int      $target_status_id    The ID of the target status to move the task to.
	 * @param int|null $previous_task_id    The ID of the task that should precede the moved task in the new status.
	 *                                      If null, the task will be placed at the start.
	 * @param string   $fallback_placement  The default position to use if no previous task is specified.
	 * @param array    $extra_field_values  Additional field values to update when moving the task.
	 *                                      Only should be used for internal operations like marking as done, not for general uses.
	 *
	 * @return float
	 * @throws InvalidArgumentException If the task does not exist, or the placement or target status is invalid.
	 * @throws Exception|\Throwable
	 */
	public function move_task(
		int $task_id,
		int $target_status_id,
		?int $previous_task_id = null,
		string $fallback_placement = 'first',
		array $extra_field_values = array()
	): float {
		// Validate fallback placement.
		if ( ! in_array( $fallback_placement, array( 'first', 'last' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid default position. Must be "first" or "last".' );
		}

		if ( ! $this->task_repository->find( $task_id ) ) {
			throw new InvalidArgumentException( 'Task not found.' );
		}

		// Check if the new status ID is valid.
		$target_status_option_field = $this->get_field_option( $target_status_id );
		if ( ! $target_status_option_field || $target_status_option_field->field_id !== DefaultTaskFields::STATUS ) {
			throw new InvalidArgumentException( 'Invalid target status ID.' );
		}

		// Determine new position based on previous task ID or default position.
		if ( ! empty( $previous_task_id ) || 'first' === $fallback_placement ) {
			// Get the position of the previous task, or start at 0.0 if placing first.
			$previous_position = 0.0;
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

			$new_position = $next_position_value
				? $this->calculate_fractional_position( $previous_position, $next_position_value, $target_status_id )
				: $previous_position + 1.0;
		} else {
			// Place at the end.
			$last_position = $this->task_repository->get_last_task_position( $task_id, $target_status_id );
			$new_position  = $last_position ? $last_position + 1.0 : 1.0;
		}

		/**
		 * Filters the calculated new position for a moved task.
		 *
		 * @param float    $new_position     The calculated new position.
		 * @param int      $task_id          The ID of the task being moved.
		 * @param int|null $previous_task_id The ID of the previous task, if any.
		 * @param int      $target_status_id The ID of the target status.
		 *
		 * @return float The new position.
		 */
		$new_position = apply_filters(
			'wpo_otd_task_calculated_new_position',
			(float) number_format( $new_position, 5, '.', '' ),
			$task_id,
			$previous_task_id,
			$target_status_id
		);

		$done_option_id = $this->task_status_role_service->get_done_field_option_id();

		// Update "done_date" automatically, if moving into the option assigned to the "done" role.
		if (
			$target_status_option_field->id === $done_option_id &&
			! isset( $extra_field_values[ DefaultTaskFields::DONE_DATE ] )
		) {
			$extra_field_values[ DefaultTaskFields::DONE_DATE ] = gmdate( DefaultTaskFields::DONE_DATE_FORMAT );
		}

		// Clear "done_date" if moving out of the option assigned to the "done" role.
		// When no done role is configured, $done_option_id is null and this branch
		// always runs — null'ing done_date is the safe default.
		if (
			$target_status_option_field->id !== $done_option_id &&
			! isset( $extra_field_values[ DefaultTaskFields::DONE_DATE ] )
		) {
			$extra_field_values[ DefaultTaskFields::DONE_DATE ] = null;
		}

		$update_data = array(
			DefaultTaskFields::STATUS   => $target_status_id,
			DefaultTaskFields::POSITION => $new_position,
		) + $extra_field_values;

		$old_status    = $this->task_field_value_repository
			->find_by_task_and_field( $task_id, DefaultTaskFields::STATUS );
		$old_status_id = $old_status ? (int) $old_status->value : null;

		$updated_fields = array(
			'status' => array(
				'old_value' => $old_status_id,
				'new_value' => $target_status_id,
			),
		);

		$this->save_field_values( $task_id, $update_data );

		/**
		 * Fires after a task has been moved to a new position.
		 *
		 * @param int   $task_id          The ID of the moved task.
		 * @param int   $target_status_id The ID of the target status.
		 * @param float $new_position     The new calculated position of the task.
		 * @param array $updated_fields   Associative array of fields that were changed,
		 *                                with 'old_value' and 'new_value' for each.
		 */
		do_action( 'wpo_otd_task_moved', $task_id, $target_status_id, $new_position, $updated_fields );

		return $new_position;
	}

	/**
	 * Save a task's field values, failing loudly when the database does not save them.
	 *
	 * A plain Exception, like a task that cannot be saved: the REST API reports it as a server error and logs it,
	 * where a RuntimeException can be shown to the user as a conflict.
	 *
	 * @param int               $task_id
	 * @param array<int, mixed> $field_values Field ID => value, as update_task_multiple_field_values() takes them.
	 *
	 * @return void
	 * @throws Exception When the values could not be saved.
	 * @throws \Throwable When the database throws.
	 */
	private function save_field_values( int $task_id, array $field_values ): void {
		if ( false !== $this->task_field_value_repository->update_task_multiple_field_values( $task_id, $field_values ) ) {
			return;
		}

		$db_error = $this->task_field_value_repository->get_last_db_error();

		throw new Exception(
			'Failed to save the task\'s field values.' . ( $db_error ? ' Database error: ' . esc_html( $db_error ) : '' )
		);
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
	private function calculate_fractional_position(
		float $previous_position,
		float $next_position,
		int $status_id
	): float {
		if ( $previous_position >= $next_position ) {
			throw new InvalidArgumentException( 'Previous position must be less than next position.' );
		}

		// Handle case where putting at the start, while the next position is greater than 1.0.
		if ( 0.0 === $previous_position && $next_position > 1.0  ) {
			return 1.0;
		}

		$position = ( $previous_position + $next_position ) / 2;

		/**
		 * Filters the precision threshold for task position calculations.
		 *
		 * @param float $precision_threshold The precision threshold.
		 *
		 * @return float The precision threshold.
		 */
		$precision_threshold = apply_filters( 'wpo_otd_task_position_precision_threshold', 0.0001 );

		// Check for precision.
		if ( abs( $next_position - $previous_position ) < $precision_threshold ) {
			// Schedule a rebalance task positions job.
			$this->schedule_rebalance_task_job( $status_id );
		}

		return $position;
	}

	/**
	 * Mark a task as finished.
	 *
	 * @param int $task_id
	 *
	 * @return bool
	 * @throws InvalidArgumentException If the task does not exist.
	 * @throws RuntimeException If no option is assigned to the "done" role or
	 *                          the configured option no longer exists.
	 * @throws Exception|\Throwable
	 */
	public function mark_task_finished( int $task_id ): bool {
		$task = $this->task_repository->find( $task_id );
		if ( ! $task ) {
			throw new InvalidArgumentException( 'Task not found.' );
		}

		$done_option_id = $this->task_status_role_service->get_done_field_option_id();

		if ( null === $done_option_id ) {
			throw new RuntimeException( 'No option is assigned to the "done" role. Configure the role assignment in plugin settings before finishing tasks.' );
		}

		$finished_status_option = $this->task_field_option_repository->find( $done_option_id );

		if ( ! $finished_status_option ) {
			throw new RuntimeException( esc_html( "The option assigned to the \"done\" role (ID $done_option_id) no longer exists." ) );
		}

		$result = $this->move_task( $task_id, $finished_status_option->id, null, 'last' );

		if ( $result ) {
			/**
			 * Fires after a task has been marked as finished.
			 *
			 * @param int $task_id The ID of the finished task.
			 */
			do_action( 'wpo_otd_task_marked_finished', $task_id );
		}

		return (bool) $result;
	}

	/**
	 * Archive a task by setting its archived_date. Status is preserved.
	 *
	 * @param int $task_id
	 *
	 * @return bool
	 * @throws InvalidArgumentException If the task does not exist.
	 * @throws Exception
	 */
	public function archive_task( int $task_id ): bool {
		$task = $this->task_repository->find( $task_id );
		if ( ! $task ) {
			throw new InvalidArgumentException( 'Task not found.' );
		}

		$existing = $this->task_field_value_repository->find_by_task_and_field(
			$task_id,
			DefaultTaskFields::ARCHIVED_DATE
		);
		if ( ! $existing ) {
			$this->task_field_value_repository->insert( array(
				'task_id'  => $task_id,
				'field_id' => DefaultTaskFields::ARCHIVED_DATE,
				'value'    => gmdate( 'Y-m-d H:i:s' ),
			) );
		}

		/**
		 * Fires after a task has been archived.
		 *
		 * @param int $task_id The ID of the archived task.
		 */
		do_action( 'wpo_otd_task_archived', $task_id );

		return true;
	}

	/**
	 * Unarchive a task by clearing its archived_date. Status is preserved.
	 *
	 * @param int $task_id
	 *
	 * @return bool
	 * @throws InvalidArgumentException If the task does not exist.
	 * @throws Exception
	 */
	public function unarchive_task( int $task_id ): bool {
		$task = $this->task_repository->find( $task_id );
		if ( ! $task ) {
			throw new InvalidArgumentException( 'Task not found.' );
		}

		$this->task_field_value_repository->delete_by_task_and_field( $task_id, DefaultTaskFields::ARCHIVED_DATE );

		/**
		 * Fires after a task has been unarchived.
		 *
		 * @param int $task_id The ID of the unarchived task.
		 */
		do_action( 'wpo_otd_task_unarchived', $task_id );

		return true;
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
	public function get_field_value( TaskFieldValue $field_value, TaskField $field ): mixed {
		if ( is_null( $field_value->value ) ) {
			return null;
		}

		$raw      = $field_value->value;
		$resolved = null;

		switch ( $field->type ) {
			case TaskFieldTypes::SELECT:
				$raw = (int) $raw;

				switch ( $field->slug ) {
					case 'status':
					case 'priority':
						$field_option = $this->task_field_option_repository->find( $raw );
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
								'id'           => $user->ID,
								'username'     => $user->user_login,
								'email'        => $user->user_email,
								'first_name'   => $user->first_name,
								'last_name'    => $user->last_name,
								'display_name' => $user->display_name,
							);
						}
						break;

					// Handle special case for 'order' field type (for the Associated Order field).
					case 'order':
						$order = wc_get_order( (int) $raw );
						if ( $order ) {
							$resolved = array(
								'id'           => $order->get_id(),
								'full_name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
								'url'          => admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
								'profile_url'  => admin_url( 'user-edit.php?user_id=' . $order->get_customer_id() ),
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
					$resolved = wp_date( 'c', $timestamp );
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
		return apply_filters( 'wpo_otd_task_get_field_value', $value, $field_value, $field );
	}

	/**
	 * Turn a field value into the string the database would store, so values can be compared.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	private function normalize_for_comparison( mixed $value ): string {
		return is_scalar( $value ) || null === $value ? (string) $value : (string) wp_json_encode( $value );
	}

	/**
	 * Schedule a rebalance task positions job for a specific status.
	 *
	 * @param int $status_id
	 *
	 * @return void
	 */
	public function schedule_rebalance_task_job( int $status_id ): void {
		if ( $status_id <= 0 ||
		     ! function_exists( 'as_has_scheduled_action' ) ||
		     ! function_exists( 'as_schedule_single_action' )
		) {
			Logger::warning( 'Invalid status ID or Action Scheduler not available. Cannot schedule rebalance task positions job.' );
			return;
		}

		$hook_key = 'wpo_otd_rebalance_task_positions';

		if (
			\as_has_scheduled_action(
				$hook_key,
				array( 'status_id' => $status_id ),
				'wpo_otd'
			)
		) {
			return;
		}

		/**
		 * Filters the scheduled time for the rebalance task positions action.
		 *
		 * @param int $timestamp The scheduled timestamp.
		 *
		 * @return int The modified timestamp.
		 */
		$timestamp = apply_filters(
			'wpo_otd_rebalance_task_positions_scheduled_time',
			strtotime( '+1 minute' )
		);

		\as_schedule_single_action(
			$timestamp,
			$hook_key,
			array( 'status_id' => $status_id ),
			'wpo_otd'
		);
	}

	/**
	 * Rebalance task positions within a specific status.
	 *
	 * @param int $status_id
	 *
	 * @return void
	 * @throws \Throwable
	 */
	public function rebalance_task_positions( int $status_id ): void {
		try {
			$this->task_repository->rebalance_positions( $status_id );
		} catch ( \Throwable $e ) {
			Logger::error( sprintf(
				'Failed to rebalance task positions for status %d: %s',
				$status_id,
				$e->getMessage()
			) );
		}
	}
}
