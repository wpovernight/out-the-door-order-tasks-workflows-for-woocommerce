<?php

namespace WPO\OTD\Tests\Integration\REST;

use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Services\CustomOrderStatusService;
use WPO\OTD\Services\TaskManagerService;

/**
 * How every endpoint answers a zero ID and an unexpected failure.
 *
 * Failures are injected by throwing from $wpdb when the endpoint touches its table.
 */
class ErrorResponsesTest extends RestTestCase {
	public function set_up() {
		parent::set_up();

		// Failing endpoints log through WooCommerce; keep that out of its log files.
		add_filter( 'wpo_otd_logging_enabled', '__return_false' );
	}

	/**
	 * @dataProvider zero_id_provider
	 */
	public function test_a_zero_id_is_a_bad_request( string $method, string $route, ?array $body, string $code ): void {
		$this->assertResponse( 400, $this->request( $method, $route, $body ), $code );
	}

	public function zero_id_provider(): array {
		$option = array( 'label' => 'X' );

		return array(
			'get task'                => array( 'GET', '/tasks/0', null, 'invalid_id' ),
			'update task'             => array( 'PUT', '/tasks/0', array( 'title' => 'X' ), 'invalid_id' ),
			'delete task'             => array( 'DELETE', '/tasks/0', null, 'invalid_id' ),
			'move task'               => array( 'POST', '/tasks/0/move', array( 'target_status_id' => 1 ), 'invalid_params' ),
			'finish task'             => array( 'POST', '/tasks/0/finish', null, 'invalid_id' ),
			'archive task'            => array( 'POST', '/tasks/0/archive', null, 'invalid_id' ),
			'unarchive task'          => array( 'POST', '/tasks/0/unarchive', null, 'invalid_id' ),
			'list options'            => array( 'GET', '/tasks/fields/0/options', null, 'invalid_field_id' ),
			'create option'           => array( 'POST', '/tasks/fields/0/options', $option, 'invalid_field_id' ),
			'update option, field'    => array( 'PUT', '/tasks/fields/0/options/1', $option, 'invalid_field_id' ),
			'update option, option'   => array( 'PUT', '/tasks/fields/1/options/0', $option, 'invalid_option_id' ),
			'delete option, field'    => array( 'DELETE', '/tasks/fields/0/options/1', null, 'invalid_field_id' ),
			'delete option, option'   => array( 'DELETE', '/tasks/fields/1/options/0', null, 'invalid_option_id' ),
			'reorder options'         => array( 'POST', '/tasks/fields/0/options/reorder', array( 'ordered_option_ids' => array( 1 ) ), 'invalid_field_id' ),
			'get custom status'       => array( 'GET', '/custom-order-statuses/0', null, 'invalid_id' ),
			'update custom status'    => array( 'PUT', '/custom-order-statuses/0', $option, 'invalid_id' ),
			'delete custom status'    => array( 'DELETE', '/custom-order-statuses/0', null, 'invalid_id' ),
		);
	}

	/**
	 * @dataProvider body_route_provider
	 */
	public function test_a_request_without_a_body_is_a_bad_request( string $method, string $route, string $code ): void {
		$response = $this->raw_request( $method, $this->fill_route( $route ), null );

		$this->assertResponse( 400, $response, $code );
	}

	/**
	 * @dataProvider body_route_provider
	 */
	public function test_a_json_list_or_null_body_is_a_bad_request( string $method, string $route, string $code ): void {
		$route = $this->fill_route( $route );

		foreach ( array( '[1, 2]', '[{"label": "X", "title": "X"}]', 'null' ) as $body ) {
			$this->assertResponse( 400, $this->raw_request( $method, $route, $body ), $code );
		}
	}

	public function body_route_provider(): array {
		return array(
			// The title is a required route argument, which WordPress finds missing before the handler runs.
			'create task'          => array( 'POST', '/tasks', 'rest_missing_callback_param' ),
			'update task'          => array( 'PUT', '/tasks/{task}', 'invalid_body' ),
			'create option'        => array( 'POST', '/tasks/fields/1/options', 'invalid_body' ),
			'update option'        => array( 'PUT', '/tasks/fields/1/options/1', 'invalid_body' ),
			'update status roles'  => array( 'PUT', '/tasks/status-roles', 'invalid_body' ),
			'create custom status' => array( 'POST', '/custom-order-statuses', 'invalid_body' ),
			'update custom status' => array( 'PUT', '/custom-order-statuses/{status}', 'invalid_body' ),
		);
	}

	/**
	 * Routes without route arguments: on the others, WordPress itself warns about a scalar body before the handler
	 * runs.
	 *
	 * @dataProvider scalar_body_route_provider
	 */
	public function test_a_json_scalar_body_is_a_bad_request( string $method, string $route ): void {
		$route = $this->fill_route( $route );

		foreach ( array( '5', '"title"', 'true' ) as $body ) {
			$response = $this->raw_request( $method, $route, $body );

			$this->assertResponse( 400, $response, 'invalid_body' );
			$this->assertSame( 'The request body must be a JSON object.', $response->get_data()['message'] );
		}
	}

	public function scalar_body_route_provider(): array {
		return array(
			'update status roles'  => array( 'PUT', '/tasks/status-roles' ),
			'create custom status' => array( 'POST', '/custom-order-statuses' ),
			'update custom status' => array( 'PUT', '/custom-order-statuses/{status}' ),
		);
	}

	/**
	 * @dataProvider form_body_provider
	 */
	public function test_a_form_encoded_body_is_a_bad_request( string $method, string $route, array $fields ): void {
		$request = new \WP_REST_Request( $method, self::NAMESPACE . $this->fill_route( $route ) );
		$request->set_header( 'Content-Type', 'application/x-www-form-urlencoded' );
		$request->set_body( http_build_query( $fields ) );
		$request->set_body_params( $fields );

		$response = rest_do_request( $request );

		$this->assertResponse( 400, $response, 'invalid_body' );
		$this->assertSame( 'The request body must be a JSON object.', $response->get_data()['message'] );
	}

	public function form_body_provider(): array {
		return array(
			// The title satisfies the route's required argument, so the handler itself refuses the body.
			'create task'          => array( 'POST', '/tasks', array( 'title' => 'X' ) ),
			'update task'          => array( 'PUT', '/tasks/{task}', array( 'title' => 'X' ) ),
			'update status roles'  => array( 'PUT', '/tasks/status-roles', array( 'done' => '1' ) ),
			'create custom status' => array( 'POST', '/custom-order-statuses', array( 'label' => 'X' ) ),
		);
	}

	public function test_an_empty_json_object_is_a_body(): void {
		$response = $this->raw_request( 'PUT', '/tasks/status-roles', '{}' );

		$this->assertResponse( 200, $response );
		$this->assertSame( array( 'done', 'undone' ), array_keys( $this->json( $response )['data'] ) );
	}

	/**
	 * @dataProvider failure_provider
	 */
	public function test_an_unexpected_failure_is_a_server_error( string $method, string $route, ?array $body, string $table, string $code ): void {
		$route = str_replace( '{task}', (string) $this->create_task(), $route );

		// Creating the task cached field options; the request must reach the database.
		self::clear_repository_caches();

		// LogicException is neither the InvalidArgumentException nor the RuntimeException some endpoints map to 4xx.
		$this->throw_on_queries( "/wpo_otd_{$table}\\b/", new \LogicException( 'Database unavailable' ) );

		$response = $this->request( $method, $route, $body );

		$this->assertResponse( 500, $response, $code );
		$this->assertStringNotContainsString( 'Database unavailable', $response->get_data()['message'], 'The cause is logged, not answered.' );
	}

	public function failure_provider(): array {
		return array(
			'list tasks'           => array( 'GET', '/tasks', null, 'tasks', 'fetch_failed' ),
			'create task'          => array( 'POST', '/tasks', array( 'title' => 'X' ), 'tasks', 'task_creation_failed' ),
			'get task'             => array( 'GET', '/tasks/{task}', null, 'tasks', 'fetch_failed' ),
			'update task'          => array( 'PUT', '/tasks/{task}', array( 'title' => 'X' ), 'tasks', 'task_update_failed' ),
			'delete task'          => array( 'DELETE', '/tasks/{task}', null, 'tasks', 'task_deletion_failed' ),
			'move task'            => array( 'POST', '/tasks/{task}/move', array( 'target_status_id' => 1 ), 'task_field_options', 'task_move_failed' ),
			'finish task'          => array( 'POST', '/tasks/{task}/finish', null, 'tasks', 'task_finish_failed' ),
			'archive task'         => array( 'POST', '/tasks/{task}/archive', null, 'tasks', 'task_archive_failed' ),
			'unarchive task'       => array( 'POST', '/tasks/{task}/unarchive', null, 'tasks', 'task_unarchive_failed' ),
			'list fields'          => array( 'GET', '/tasks/fields', null, 'task_fields', 'fetch_failed' ),
			'list options by id'   => array( 'GET', '/tasks/fields/1/options', null, 'task_field_options', 'fetch_failed' ),
			'list options by slug' => array( 'GET', '/tasks/fields/status/options', null, 'task_fields', 'fetch_failed' ),
			'create option'        => array( 'POST', '/tasks/fields/1/options', array( 'label' => 'X' ), 'task_field_options', 'creation_failed' ),
			'update option'        => array( 'PUT', '/tasks/fields/1/options/1', array( 'label' => 'X' ), 'task_field_options', 'update_failed' ),
			'delete option'        => array( 'DELETE', '/tasks/fields/1/options/1', null, 'task_field_options', 'deletion_failed' ),
			'reorder options'      => array( 'POST', '/tasks/fields/1/options/reorder', array( 'ordered_option_ids' => array( 1, 2, 3 ) ), 'task_fields', 'update_failed' ),
			'update status roles'  => array( 'PUT', '/tasks/status-roles', array( 'done' => 1 ), 'task_field_options', 'update_failed' ),
			'create custom status' => array( 'POST', '/custom-order-statuses', array( 'label' => 'X' ), 'custom_statuses', 'creation_failed' ),
			'update custom status' => array( 'PUT', '/custom-order-statuses/1', array( 'label' => 'X' ), 'custom_statuses', 'update_failed' ),
			'delete custom status' => array( 'DELETE', '/custom-order-statuses/1', null, 'custom_statuses', 'deletion_failed' ),
		);
	}

	/**
	 * @dataProvider custom_status_failure_provider
	 */
	public function test_a_failed_custom_status_change_answers_a_generic_message_and_logs_the_cause( string $method, string $route, ?array $body, string $code, string $message ): void {
		$logged = array();
		add_filter(
			'wpo_otd_logging_enabled',
			static function ( $enabled, $level, $text ) use ( &$logged ) {
				$logged[] = array( $level, $text );

				return false;
			},
			20,
			3
		);

		$id = $this->make( CustomOrderStatusService::class )->create( array( 'label' => 'Packed' ) )->id;
		$this->throw_on_queries( '/wpo_otd_custom_statuses\b/', new \RuntimeException( 'Table wp_wpo_otd_custom_statuses is corrupt' ) );

		$response = $this->request( $method, str_replace( '{status}', (string) $id, $route ), $body );

		$this->assertResponse( 500, $response, $code );
		$this->assertSame( $message, $response->get_data()['message'] );
		$this->assertCount( 1, $logged );
		$this->assertSame( 'error', $logged[0][0] );
		$this->assertStringContainsString( 'Table wp_wpo_otd_custom_statuses is corrupt', $logged[0][1] );
	}

	/**
	 * @dataProvider custom_status_failure_provider
	 */
	public function test_a_custom_status_change_that_hits_an_error_is_a_server_error( string $method, string $route, ?array $body, string $code, string $message ): void {
		$id = $this->make( CustomOrderStatusService::class )->create( array( 'label' => 'Packed' ) )->id;
		$this->throw_on_queries( '/wpo_otd_custom_statuses\b/', new \TypeError( 'Cannot assign null to property' ) );

		$response = $this->request( $method, str_replace( '{status}', (string) $id, $route ), $body );

		$this->assertResponse( 500, $response, $code );
		$this->assertSame( $message, $response->get_data()['message'] );
	}

	public function custom_status_failure_provider(): array {
		return array(
			'create' => array( 'POST', '/custom-order-statuses', array( 'label' => 'X' ), 'creation_failed', 'Failed to create custom order status.' ),
			'update' => array( 'PUT', '/custom-order-statuses/{status}', array( 'label' => 'X' ), 'update_failed', 'Failed to update custom order status.' ),
			'delete' => array( 'DELETE', '/custom-order-statuses/{status}', null, 'deletion_failed', 'Failed to delete custom order status.' ),
		);
	}

	public function test_a_move_that_yields_no_position_is_a_server_error(): void {
		$id = $this->create_task();
		add_filter( 'wpo_otd_task_calculated_new_position', '__return_zero' );

		$this->assertResponse(
			500,
			$this->request( 'POST', "/tasks/{$id}/move", array( 'target_status_id' => $this->status_option_id( 'in_progress' ) ) ),
			'task_move_failed'
		);
	}

	/**
	 * Send a request with a body exactly as written, as JSON.
	 *
	 * @param string      $method
	 * @param string      $route Route below the plugin namespace.
	 * @param string|null $body  Null sends no body.
	 *
	 * @return \WP_REST_Response
	 */
	private function raw_request( string $method, string $route, ?string $body ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, self::NAMESPACE . $route );
		$request->set_header( 'Content-Type', 'application/json' );

		if ( null !== $body ) {
			$request->set_body( $body );
		}

		return rest_do_request( $request );
	}

	/**
	 * Put a real task and custom status in a route's {task} and {status}.
	 *
	 * @param string $route
	 *
	 * @return string
	 */
	private function fill_route( string $route ): string {
		if ( str_contains( $route, '{task}' ) ) {
			$route = str_replace( '{task}', (string) $this->create_task(), $route );
		}

		if ( str_contains( $route, '{status}' ) ) {
			$route = str_replace( '{status}', (string) $this->make( CustomOrderStatusService::class )->create( array( 'label' => 'Packed' ) )->id, $route );
		}

		return $route;
	}

	/**
	 * Create a task through the service.
	 *
	 * @return int
	 */
	private function create_task(): int {
		return $this->make( TaskManagerService::class )->create_task(
			'Task',
			'',
			array(
				array(
					'field_id'   => DefaultTaskFields::STATUS,
					'field_slug' => 'status',
					'value'      => $this->status_option_id( 'not_started' ),
				),
			)
		)['id'];
	}
}
