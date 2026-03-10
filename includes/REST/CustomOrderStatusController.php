<?php

namespace WPO\AOM\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WPO\AOM\Services\CustomOrderStatusService;

defined( 'ABSPATH' ) || exit;

class CustomOrderStatusController extends BaseRestController {
	protected string $resource_name = 'custom-order-statuses';

	/**
	 * Register the REST API routes for this controller.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		/**
		 * GET /wp-json/wpo-aom/v1/custom-order-statuses
		 * POST /wp-json/wpo-aom/v1/custom-order-statuses
		 *
		 * Description: Retrieve a list of all custom order statuses.
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				)
			)
		);

		/**
		 * Single item endpoints:
		 * GET /wp-json/wpo-aom/v1/custom-order-statuses/{id}
		 * PUT /wp-json/wpo-aom/v1/custom-order-statuses/{id}
		 * DELETE /wp-json/wpo-aom/v1/custom-order-statuses/{id}
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				)
			)
		);
	}

	/**
	 * Handle GET requests to retrieve a list of all custom order statuses.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( WP_REST_Request $request ) {
		/** @var CustomOrderStatusService $custom_order_status_service */
		$custom_order_status_service = WPO_AOM()->get_service( CustomOrderStatusService::class );
		$custom_order_statuses       = $custom_order_status_service->all();

		return rest_ensure_response( $custom_order_statuses );
	}

	/**
	 * Handle POST requests to create a new custom order status.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		$data = $request->get_json_params();

		$errors = $this->validate( $data, array(
			'label'      => 'required|string',
			'status_key' => 'string|regex:/^[a-z0-9_-]+$/i',
			'background' => 'string|regex:/^#([a-f0-9]{3}|[a-f0-9]{6})$/i',
			'foreground' => 'string|regex:/^#([a-f0-9]{3}|[a-f0-9]{6})$/i',
		) );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid data provided', 'wpo-aom' ), array(
				'status' => 400,
				'errors' => $errors
			) );
		}

		/** @var CustomOrderStatusService $custom_order_status_service */
		$custom_order_status_service = WPO_AOM()->get_service( CustomOrderStatusService::class );

		try {
			$new_status = $custom_order_status_service->create( $data );
		} catch ( \Exception $e ) {
			return new WP_Error( 'creation_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		return rest_ensure_response( $new_status );
	}

	/**
	 * Handle GET requests to retrieve a single custom order status by ID.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( $id <= 0 ) {
			return new WP_Error( 'invalid_id', __( 'Invalid ID provided', 'wpo-aom' ), array( 'status' => 400 ) );
		}

		/** @var CustomOrderStatusService $custom_order_status_service */
		$custom_order_status_service = WPO_AOM()->get_service( CustomOrderStatusService::class );
		$status                      = $custom_order_status_service->find( $id );

		if ( ! $status ) {
			return new WP_Error( 'not_found', __( 'Custom order status not found', 'wpo-aom' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $status );
	}

	/**
	 * Handle PUT requests to update an existing custom order status by ID.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( $id <= 0 ) {
			return new WP_Error( 'invalid_id', __( 'Invalid ID provided', 'wpo-aom' ), array( 'status' => 400 ) );
		}

		$data = $request->get_json_params();

		$errors = $this->validate( $data, array(
			'label'      => 'string',
			'status_key' => 'string|regex:/^[a-z0-9_-]+$/i',
			'background' => 'string|regex:/^#([a-f0-9]{3}|[a-f0-9]{6})$/i',
			'foreground' => 'string|regex:/^#([a-f0-9]{3}|[a-f0-9]{6})$/i',
		) );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid data provided', 'wpo-aom' ), array(
				'status' => 400,
				'errors' => $errors
			) );
		}

		/** @var CustomOrderStatusService $custom_order_status_service */
		$custom_order_status_service = WPO_AOM()->get_service( CustomOrderStatusService::class );

		try {
			$updated_status = $custom_order_status_service->update( $id, $data );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'not_found', $e->getMessage(), array( 'status' => 404 ) );
		} catch ( \Exception $e ) {
			return new WP_Error( 'update_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		return rest_ensure_response( $updated_status );
	}

	/**
	 * Handle DELETE requests to remove a custom order status by ID.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( $id <= 0 ) {
			return new WP_Error( 'invalid_id', __( 'Invalid ID provided', 'wpo-aom' ), array( 'status' => 400 ) );
		}

		/** @var CustomOrderStatusService $custom_order_status_service */
		$custom_order_status_service = WPO_AOM()->get_service( CustomOrderStatusService::class );

		try {
			$custom_order_status_service->delete( $id );
		} catch ( \Exception $e ) {
			return new WP_Error( 'deletion_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Custom order status deleted successfully', 'wpo-aom' )
		) );
	}
}
