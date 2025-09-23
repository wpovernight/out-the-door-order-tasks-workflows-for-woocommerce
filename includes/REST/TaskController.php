<?php

namespace WPO\AOM\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WPO\AOM\Services\TaskManagementService;

defined( 'ABSPATH' ) || exit;

class TaskController extends BaseRestController {
	protected string $resource_name = 'tasks';

	/**
	 * Register the REST API routes for tasks.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_item_schema(), WP_REST_Server::READABLE ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_item_schema(), WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_item_schema(), WP_REST_Server::READABLE ),
					'schema'              => array( $this, 'get_item_schema' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_item_schema(), WP_REST_Server::EDITABLE ),
					'schema'              => array( $this, 'get_item_schema' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_item_schema(), WP_REST_Server::DELETABLE ),
					'schema'              => array( $this, 'get_item_schema' ),
				),
			),
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
		/** @var TaskManagementService $task_management_service */
		$task_management_service = WPO_AOM()->get_service( TaskManagementService::class );
		$tasks                   = $task_management_service->get_all_tasks_with_fields();

		// ToDo: Add pagination, filtering, etc.

		return rest_ensure_response( $tasks );
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
			'title'       => 'required|string',
			'description' => 'string',
		) );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_data', 'Invalid data provided', array(
				'status' => 400,
				'errors' => $errors
			) );
		}

		// ToDo: Maybe adding support for fields during creation?

		/** @var TaskManagementService $task_management_service */
		$task_management_service = WPO_AOM()->get_service( TaskManagementService::class );

		try {
			$task = $task_management_service->create_task( $data );
		} catch ( \Exception $e ) {
			return new WP_Error( 'task_creation_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

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
		$id = (int) $request->get_param( 'id' );

		if ( $id <= 0 ) {
			return new WP_Error( 'invalid_id', 'Invalid task ID provided', array( 'status' => 400 ) );
		}

		/** @var TaskManagementService $task_management_service */
		$task_management_service = WPO_AOM()->get_service( TaskManagementService::class );
		$task                    = $task_management_service->get_task( $id );

		if ( ! $task ) {
			return new WP_Error( 'not_found', 'Task not found', array( 'status' => 404 ) );
		}

		return rest_ensure_response( $task );
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
		) );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_data', 'Invalid data provided', array(
				'status' => 400,
				'errors' => $errors
			) );
		}

		/** @var TaskManagementService $task_management_service */
		$task_management_service = WPO_AOM()->get_service( TaskManagementService::class );

		try {
			$task = $task_management_service->update_task( $id, $data );
		} catch ( \Exception $e ) {
			return new WP_Error( 'task_update_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

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

		/** @var TaskManagementService $task_management_service */
		$task_management_service = WPO_AOM()->get_service( TaskManagementService::class );

		try {
			$task_management_service->delete_task( $id );
		} catch ( \Exception $e ) {
			return new WP_Error( 'task_deletion_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'message' => 'Task deleted successfully' ) );
	}

	/**
	 * Get the task schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema(): array {
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
}
