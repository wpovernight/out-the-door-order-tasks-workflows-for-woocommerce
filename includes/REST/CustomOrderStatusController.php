<?php

namespace WPO\OTD\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WPO\OTD\Core\Logger;
use WPO\OTD\Services\CustomOrderStatusService;

defined( 'ABSPATH' ) || exit;

class CustomOrderStatusController extends BaseRestController {
	protected string $resource_name = 'custom-order-statuses';

	private readonly CustomOrderStatusService $custom_order_status_service;

	/**
	 * Constructor.
	 *
	 * @param CustomOrderStatusService $custom_order_status_service
	 */
	public function __construct( CustomOrderStatusService $custom_order_status_service ) {
		$this->custom_order_status_service = $custom_order_status_service;
	}

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
					'args'                => $this->pagination_args(),
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
	 * Handle GET requests to retrieve a list of all custom order statuses, with opt-in pagination.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Paginate only when page/per_page are provided, else full list.
		$pagination = $this->pagination_params( $request );
		$paginator  = $this->custom_order_status_service->list(
			$pagination['page'] ?? null,
			$pagination['per_page'] ?? null
		);

		return $this->respond_paginated( $paginator->items(), $paginator, $pagination );
	}

	/**
	 * Handle POST requests to create a new custom order status.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();

		$errors = $this->validate( $data, array(
			'label'      => 'required|string|max:255',
			'status_key' => 'string|max:64|regex:/^[a-z0-9_-]+$/i',
			'background' => 'string|regex:/^#([a-f0-9]{3}|[a-f0-9]{6})$/i',
			'foreground' => 'string|regex:/^#([a-f0-9]{3}|[a-f0-9]{6})$/i',
		) );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid data provided', 'out-the-door-order-tasks-workflows-for-woocommerce' ), array(
				'status' => 400,
				'errors' => $errors
			) );
		}

		$custom_order_status_service = $this->custom_order_status_service;

		try {
			$new_status = $custom_order_status_service->create( $data );
		} catch ( \Exception $e ) {
			Logger::error( 'Custom order status creation failed: ' . $e->getMessage() );
			return new WP_Error( 'creation_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		return $this->respond( $new_status );
	}

	/**
	 * Handle GET requests to retrieve a single custom order status by ID.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (int) $request->get_param( 'id' );

		if ( $id <= 0 ) {
			return new WP_Error( 'invalid_id', __( 'Invalid ID provided', 'out-the-door-order-tasks-workflows-for-woocommerce' ), array( 'status' => 400 ) );
		}

		$custom_order_status_service = $this->custom_order_status_service;
		$status                      = $custom_order_status_service->find( $id );

		if ( ! $status ) {
			return new WP_Error( 'not_found', __( 'Custom order status not found', 'out-the-door-order-tasks-workflows-for-woocommerce' ), array( 'status' => 404 ) );
		}

		return $this->respond( $status );
	}

	/**
	 * Handle PUT requests to update an existing custom order status by ID.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (int) $request->get_param( 'id' );

		if ( $id <= 0 ) {
			return new WP_Error( 'invalid_id', __( 'Invalid ID provided', 'out-the-door-order-tasks-workflows-for-woocommerce' ), array( 'status' => 400 ) );
		}

		$data = $request->get_json_params();

		$errors = $this->validate( $data, array(
			'label'      => 'string|max:255',
			'status_key' => 'string|max:64|regex:/^[a-z0-9_-]+$/i',
			'background' => 'string|regex:/^#([a-f0-9]{3}|[a-f0-9]{6})$/i',
			'foreground' => 'string|regex:/^#([a-f0-9]{3}|[a-f0-9]{6})$/i',
		) );

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid data provided', 'out-the-door-order-tasks-workflows-for-woocommerce' ), array(
				'status' => 400,
				'errors' => $errors
			) );
		}

		$custom_order_status_service = $this->custom_order_status_service;

		try {
			$updated_status = $custom_order_status_service->update( $id, $data );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'not_found', $e->getMessage(), array( 'status' => 404 ) );
		} catch ( \Exception $e ) {
			Logger::error( 'Custom order status update failed: ' . $e->getMessage() );
			return new WP_Error( 'update_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		return $this->respond( $updated_status );
	}

	/**
	 * Handle DELETE requests to remove a custom order status by ID.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (int) $request->get_param( 'id' );

		if ( $id <= 0 ) {
			return new WP_Error( 'invalid_id', __( 'Invalid ID provided', 'out-the-door-order-tasks-workflows-for-woocommerce' ), array( 'status' => 400 ) );
		}

		$custom_order_status_service = $this->custom_order_status_service;

		try {
			$custom_order_status_service->request_deletion( $id );
		} catch ( \Exception $e ) {
			Logger::error( 'Custom order status deletion failed: ' . $e->getMessage() );
			return new WP_Error( 'deletion_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		return $this->respond( array(
			'success' => true,
			'message' => __( 'Custom order status deleted successfully', 'out-the-door-order-tasks-workflows-for-woocommerce' )
		) );
	}
}
