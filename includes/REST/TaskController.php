<?php

namespace WPO\AOM\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WPO\AOM\Core\Logger;
use WPO\AOM\Services\TaskManagerService;

defined( 'ABSPATH' ) || exit;

class TaskController extends BaseRestController {
	protected string $resource_name = 'tasks';

	/**
	 * Register the REST API routes for tasks.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		/**
		 * Collection endpoints:
		 * GET  /{namespace}/tasks  -> returns array of task objects.
		 * POST /{namespace}/tasks  -> returns created task object.
		 *
		 * Both enforce permission_callback and validate args against item schema.
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_task_schema(), WP_REST_Server::READABLE ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_task_schema(), WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_task_schema' ),
			)
		);

		/**
		 * Single item endpoints:
		 * GET    /{namespace}/tasks/{id}  -> returns single task object.
		 * PUT    /{namespace}/tasks/{id}  -> returns updated task object.
		 * DELETE /{namespace}/tasks/{id}  -> returns success message.
		 *
		 * All enforce permission_callback and validate args against item schema.
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_task_schema(), WP_REST_Server::READABLE ),
					'schema'              => array( $this, 'get_task_schema' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_task_schema(), WP_REST_Server::EDITABLE ),
					'schema'              => array( $this, 'get_task_schema' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_task_schema(), WP_REST_Server::DELETABLE ),
					'schema'              => array( $this, 'get_task_schema' ),
				),
			),
		);

		/**
		 * Task movement endpoint:
		 * POST /{namespace}/tasks/{id}/move -> moves a task to a new position/status.
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<id>[\d]+)/move',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'move_task' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		/**
		 * Task finish endpoint:
		 * POST /{namespace}/tasks/{id}/finish -> marks a task as finished.
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<id>[\d]+)/finish',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'finish_task' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				)
			)
		);

		/**
		 * Task archive endpoint:
		 * POST /{namespace}/tasks/{id}/archive -> archives a task.
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<id>[\d]+)/archive',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'archive_task' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				)
			)
		);

		/**
		 * Task unarchive endpoint:
		 * POST /{namespace}/tasks/{id}/unarchive -> unarchives a task.
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<id>[\d]+)/unarchive',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'unarchive_task' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				)
			)
		);

		/**
		 * Field endpoints:
		 * GET /{namespace}/tasks/fields -> returns all task fields.
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/fields',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_task_fields' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);


		/**
		 * Field options endpoints:
		 */

		// GET /{namespace}/tasks/fields/{field_id}/options -> returns options for a specific field.
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/fields/(?P<field_id>[\d]+)/options',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_field_options' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_field_option_schema(), WP_REST_Server::READABLE ),
					'schema'              => array( $this, 'get_field_option_schema' ),
				),
			)
		);

		// GET /{namespace}/tasks/fields/{field_slug}/options -> returns options for a specific field by slug.
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/fields/(?P<field_slug>[\w-]+)/options',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_field_options_by_slug' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_field_option_schema(), WP_REST_Server::READABLE ),
					'schema'              => array( $this, 'get_field_option_schema' ),
				),
			)
		);

		// POST /{namespace}/tasks/fields/{field_id}/options/reorder -> reorder field options.
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/fields/(?P<field_id>[\d]+)/options/reorder',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reorder_field_options' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'ordered_option_ids' => array(
							'required'          => true,
							'type'              => 'array',
							'items'             => array( 'type' => 'integer' ),
							'description'       => __( 'Array of option IDs in desired order.', 'wpo-aom' ),
							'validate_callback' => function ( $param ) {
								return is_array( $param ) && ! empty( $param );
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Get all tasks with their fields.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( WP_REST_Request $request ) {
		try {
			/** @var TaskManagerService $task_manager_service */
			$task_manager_service = WPO_AOM()->get_service( TaskManagerService::class );
			$tasks                = $task_manager_service->get_all_tasks_with_fields();

			// ToDo: Add pagination, filtering, etc.

			/**
			 * Allow modifying the tasks response before it's returned.
			 *
			 * @param array           $tasks    The array of task objects.
			 * @param WP_REST_Request $request  The original REST request object.
			 */
			$tasks = apply_filters( 'wpo_aom_rest_prepare_tasks', $tasks, $request );

			return rest_ensure_response( $tasks );
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to fetch tasks: ' . $e->getMessage() );
			return new WP_Error( 'fetch_failed', 'Failed to fetch tasks.', array( 'status' => 500 ) );
		}
	}

	/**
	 * Create a new task.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		$data = $request->get_json_params();

		$errors = $this->validate( $data, array(
			'title'        => 'required|string',
			'description'  => 'string',
			'field_values' => 'array',
		) );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_data', 'Invalid data provided', array(
				'status' => 400,
				'errors' => $errors
			) );
		}

		/** @var TaskManagerService $task_manager_service */
		$task_manager_service = WPO_AOM()->get_service( TaskManagerService::class );

		try {
			$task = $task_manager_service->create_task(
				$data['title'],
				$data['description'] ?? '',
				$data['field_values'] ?? array()
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Task creation failed: ' . $e->getMessage() );
			return new WP_Error( 'task_creation_failed', __( 'Failed to create task.', 'wpo-aom' ), array( 'status' => 500 ) );
		}

		/**
		 * Allow modifying the created task response.
		 *
		 * @param array           $task     The created task object.
		 * @param WP_REST_Request $request  The original REST request object.
		 */
		$task = apply_filters( 'wpo_aom_rest_prepare_task', $task, $request );

		return rest_ensure_response( $task );
	}

	/**
	 * Get a single task by ID.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		$id             = (int) $request->get_param( 'id' );
		$include_fields = $request->get_param( 'include_fields' ) ?? true;

		if ( $id <= 0 ) {
			return new WP_Error( 'invalid_id', 'Invalid task ID provided', array( 'status' => 400 ) );
		}

		try {
			/** @var TaskManagerService $task_manager_service */
			$task_manager_service = WPO_AOM()->get_service( TaskManagerService::class );
			$task                 = $include_fields
				? $task_manager_service->get_task_with_fields( $id )
				: $task_manager_service->get_task( $id );

			if ( ! $task ) {
				return new WP_Error( 'not_found', 'Task not found', array( 'status' => 404 ) );
			}

			/**
			 * Allow modifying the task response.
			 *
			 * @param array           $task     The task object.
			 * @param WP_REST_Request $request  The original REST request object.
			 */
			$task = apply_filters( 'wpo_aom_rest_prepare_task', $task, $request );

			return rest_ensure_response( $task );
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to fetch task: ' . $e->getMessage() );
			return new WP_Error( 'fetch_failed', 'Failed to fetch task.', array( 'status' => 500 ) );
		}
	}

	/**
	 * Update a task by ID.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( $id <= 0 ) {
			return new WP_Error( 'invalid_id', 'Invalid task ID provided', array( 'status' => 400 ) );
		}

		$data = $request->get_json_params();

		$errors = $this->validate( $data, array(
			'title'       => 'string',
			'description' => 'string',
			'field_values' => 'array',
		) );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_data', 'Invalid data provided', array(
				'status' => 400,
				'errors' => $errors
			) );
		}

		/** @var TaskManagerService $task_manager_service */
		$task_manager_service = WPO_AOM()->get_service( TaskManagerService::class );

		try {
			$task = $task_manager_service->update_task( $id, $data, $data['field_values'] ?? null );
		} catch ( \Throwable $e ) {
			Logger::error( 'Task update failed: ' . $e->getMessage() );
			return new WP_Error( 'task_update_failed', __( 'Failed to update task.', 'wpo-aom' ), array( 'status' => 500 ) );
		}

		/**
		 * Allow modifying the updated task response.
		 *
		 * @param array           $task     The updated task object.
		 * @param WP_REST_Request $request  The original REST request object.
		 */
		$task = apply_filters( 'wpo_aom_rest_prepare_task', $task, $request );

		return rest_ensure_response( $task );
	}

	/**
	 * Delete a task by ID.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_item( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( $id <= 0 ) {
			return new WP_Error( 'invalid_id', 'Invalid task ID provided', array( 'status' => 400 ) );
		}

		/** @var TaskManagerService $task_manager_service */
		$task_manager_service = WPO_AOM()->get_service( TaskManagerService::class );

		try {
			$task_manager_service->delete_task( $id );
		} catch ( \Exception $e ) {
			Logger::error( 'Task deletion failed: ' . $e->getMessage() );
			return new WP_Error( 'task_deletion_failed', __( 'Failed to delete task.', 'wpo-aom' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'message' => 'Task deleted successfully' ) );
	}

	/**
	 * Get the task schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_task_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'task',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'description' => __( 'Task ID.', 'wpo-aom' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'title'       => array(
					'description' => __( 'Task title.', 'wpo-aom' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'create' ),
					'required'    => true,
				),
				'description' => array(
					'description' => __( 'Task description.', 'wpo-aom' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'create' ),
				),
				'created_at'  => array(
					'description' => __( 'Task creation timestamp.', 'wpo-aom' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'updated_at'  => array(
					'description' => __( 'Task last update timestamp.', 'wpo-aom' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
			),
		);
	}

	/**
	 * Get all task fields.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return mixed
	 */
	public function get_task_fields( WP_REST_Request $request ) {
		try {
			/** @var TaskManagerService $task_manager_service */
			$task_manager_service = WPO_AOM()->get_service( TaskManagerService::class );
			$fields               = $task_manager_service->get_all_fields();

			return rest_ensure_response( $fields );
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to fetch task fields: ' . $e->getMessage() );
			return new WP_Error( 'fetch_failed', 'Failed to fetch task fields.', array( 'status' => 500 ) );
		}
	}

	/**
	 * Get options for a specific field.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_field_options( WP_REST_Request $request ) {
		$field_id = (int) $request->get_param( 'field_id' );

		if ( $field_id <= 0 ) {
			return new WP_Error( 'invalid_field_id', 'Invalid field ID provided', array( 'status' => 400 ) );
		}

		try {
			/** @var TaskManagerService $task_manager_service */
			$task_manager_service = WPO_AOM()->get_service( TaskManagerService::class );
			$options              = $task_manager_service->get_field_options_by_field_id( $field_id );

			if ( empty( $options ) ) {
				return new WP_Error( 'not_found', 'Field not found or has no options', array( 'status' => 404 ) );
			}

			return rest_ensure_response( $options );
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to fetch field options: ' . $e->getMessage() );
			return new WP_Error( 'fetch_failed', 'Failed to fetch field options.', array( 'status' => 500 ) );
		}
	}

	/**
	 * Get options for a specific field by slug.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_field_options_by_slug( WP_REST_Request $request ) {
		$field_slug = $request->get_param( 'field_slug' );

		if ( empty( $field_slug ) || ! is_string( $field_slug ) ) {
			return new WP_Error( 'invalid_field_slug', 'Invalid field slug provided', array( 'status' => 400 ) );
		}

		try {
			/** @var TaskManagerService $task_manager_service */
			$task_manager_service = WPO_AOM()->get_service( TaskManagerService::class );
			$options              = $task_manager_service->get_field_options_by_field_slug( $field_slug );

			if ( empty( $options ) ) {
				return new WP_Error( 'not_found', 'Field not found or has no options', array( 'status' => 404 ) );
			}

			return rest_ensure_response( $options );
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to fetch field options: ' . $e->getMessage() );
			return new WP_Error( 'fetch_failed', 'Failed to fetch field options.', array( 'status' => 500 ) );
		}
	}

	/**
	 * Reorder field options by updating their position values.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function reorder_field_options( WP_REST_Request $request ) {
		$field_id           = (int) $request->get_param( 'field_id' );
		$ordered_option_ids = $request->get_param( 'ordered_option_ids' );

		// Validate field_id.
		if ( $field_id <= 0 ) {
			return new WP_Error( 'invalid_field_id', 'Invalid field ID provided', array( 'status' => 400 ) );
		}

		// Validate ordered_option_ids.
		if ( ! is_array( $ordered_option_ids ) || empty( $ordered_option_ids ) ) {
			return new WP_Error( 'invalid_option_ids', 'Invalid option IDs provided', array( 'status' => 400 ) );
		}

		/** @var TaskManagerService $task_manager_service */
		$task_manager_service = WPO_AOM()->get_service( TaskManagerService::class );

		try {
			$task_manager_service->update_field_option_positions( $field_id, $ordered_option_ids );

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Field option positions updated successfully', 'wpo-aom' ),
				)
			);
		} catch ( \InvalidArgumentException $e ) {
			// Validation errors (field not found, invalid option IDs, etc.).
			return new WP_Error(
				'invalid_data',
				$e->getMessage(),
				array( 'status' => 400 )
			);
		} catch ( \Exception $e ) {
			// Unexpected errors (database issues, etc.).
			Logger::error( 'Failed to reorder field options: ' . $e->getMessage() );

			return new WP_Error(
				'update_failed',
				'Failed to update field option positions',
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get the field option schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_field_option_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'field_option',
			'type'       => 'object',
			'properties' => array(
				'id'       => array(
					'description' => __( 'Option ID.', 'wpo-aom' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'field_id' => array(
					'description' => __( 'Field ID this option belongs to.', 'wpo-aom' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'label'    => array(
					'description' => __( 'Option label.', 'wpo-aom' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'color'    => array(
					'description' => __( 'Option color.', 'wpo-aom' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'position' => array(
					'description' => __( 'Option position for ordering.', 'wpo-aom' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);
	}

	/**
	 * Move a task to a new position/status.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 * @throws \Throwable
	 */
	public function move_task( WP_REST_Request $request ) {
		$task_id          = (int) $request->get_param( 'id' );
		$previous_task_id = $request->get_param( 'previous_task_id' ) ? (int) $request->get_param( 'previous_task_id' ) : null;
		$target_status_id = (int) $request->get_param( 'target_status_id' );

		if ( $task_id <= 0 || $target_status_id <= 0 ) {
			return new WP_Error(
				'invalid_params',
				'Invalid task ID or target status ID provided',
				array( 'status' => 400 )
			);
		}

		/** @var TaskManagerService $task_service */
		$task_service = WPO_AOM()->get_service( TaskManagerService::class );

		try {
			$new_position = $task_service->move_task( $task_id, $target_status_id, $previous_task_id );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'invalid_params', $e->getMessage(), array( 'status' => 400 ) );
		} catch ( \Exception $e ) {
			Logger::error( 'Task move failed: ' . $e->getMessage() );
			return new WP_Error( 'task_move_failed', __( 'Failed to move task.', 'wpo-aom' ), array( 'status' => 500 ) );
		}

		if ( ! $new_position ) {
			return new WP_Error( 'task_move_failed', __( 'Failed to move task.', 'wpo-aom' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'new_position' => $new_position ) );
	}

	/**
	 * Mark a task as finished.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 * @throws \Exception
	 */
	public function finish_task( WP_REST_Request $request ) {
		$task_id = (int) $request->get_param( 'id' );

		if ( $task_id <= 0 ) {
			return new WP_Error( 'invalid_id', 'Invalid task ID provided', array( 'status' => 400 ) );
		}

		/** @var TaskManagerService $task_service */
		$task_service = WPO_AOM()->get_service( TaskManagerService::class );

		try {
			$task_service->mark_task_finished( $task_id );
		} catch ( \RuntimeException $e ) {
			return new WP_Error( 'task_finish_failed', $e->getMessage(), array( 'status' => 404 ) );
		} catch ( \Exception $e ) {
			Logger::error( 'Task finish failed: ' . $e->getMessage() );
			return new WP_Error( 'task_finish_failed', __( 'Failed to finish task.', 'wpo-aom' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Task marked as finished',
		) );
	}

	/**
	 * Archive a task.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 * @throws \Exception
	 */
	public function archive_task( WP_REST_Request $request ) {
		$task_id = (int) $request->get_param( 'id' );

		if ( $task_id <= 0 ) {
			return new WP_Error( 'invalid_id', 'Invalid task ID provided', array( 'status' => 400 ) );
		}

		/** @var TaskManagerService $task_service */
		$task_service = WPO_AOM()->get_service( TaskManagerService::class );

		try {
			$task_service->archive_task( $task_id );
		} catch ( \RuntimeException $e ) {
			return new WP_Error( 'task_archive_failed', $e->getMessage(), array( 'status' => 404 ) );
		} catch ( \Exception $e ) {
			Logger::error( 'Task archive failed: ' . $e->getMessage() );
			return new WP_Error( 'task_archive_failed', __( 'Failed to archive task.', 'wpo-aom' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Task archived',
		) );
	}

	/**
	 * Unarchive a task.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 * @throws \Exception
	 */
	public function unarchive_task( WP_REST_Request $request ) {
		$task_id = (int) $request->get_param( 'id' );

		if ( $task_id <= 0 ) {
			return new WP_Error( 'invalid_id', 'Invalid task ID provided', array( 'status' => 400 ) );
		}

		/** @var TaskManagerService $task_service */
		$task_service = WPO_AOM()->get_service( TaskManagerService::class );

		try {
			$task_service->unarchive_task( $task_id );
		} catch ( \RuntimeException $e ) {
			return new WP_Error( 'task_unarchive_failed', $e->getMessage(), array( 'status' => 404 ) );
		} catch ( \Exception $e ) {
			Logger::error( 'Task unarchive failed: ' . $e->getMessage() );
			return new WP_Error( 'task_unarchive_failed', __( 'Failed to unarchive task.', 'wpo-aom' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Task unarchived',
		) );
	}
}
