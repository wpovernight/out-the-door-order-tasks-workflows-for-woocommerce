<?php

namespace WPO\OTD\Tests\Integration\REST;

use ReflectionFunction;
use WP_REST_Request;
use WP_REST_Response;
use WPO\OTD\Core\Providers\CoreServiceProvider;
use WPO\OTD\REST\CustomOrderStatusController;
use WPO\OTD\REST\FulfillmentController;
use WPO\OTD\REST\TaskController;
use WPO\OTD\Tests\IntegrationTestCase;

/**
 * Base class for tests that dispatch requests to the plugin's REST routes.
 *
 * The routes registered at boot use the plugin's long-lived services, which cache settings and
 * statuses across tests. Each test gets a fresh REST server whose routes use controllers from
 * the test's own container, and runs as a shop manager.
 */
abstract class RestTestCase extends IntegrationTestCase {
	protected const NAMESPACE = '/wc/v3/wpo/otd';

	public function set_up() {
		global $wp_rest_server;

		parent::set_up();

		$this->remove_boot_routes();

		add_action(
			'rest_api_init',
			function () {
				$this->make( TaskController::class )->register_routes();
				$this->make( FulfillmentController::class )->register_routes();
				$this->make( CustomOrderStatusController::class )->register_routes();
			}
		);

		$wp_rest_server = null;
		rest_get_server();

		$this->act_as( 'shop_manager' );
	}

	public function tear_down() {
		global $wp_rest_server;

		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Dispatch a request to one of the plugin's routes.
	 *
	 * @param string     $method
	 * @param string     $route Route below the plugin namespace, e.g. "/tasks/3".
	 * @param array|null $body  JSON body.
	 * @param array      $query Query string parameters.
	 *
	 * @return WP_REST_Response
	 */
	protected function request( string $method, string $route, ?array $body = null, array $query = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, self::NAMESPACE . $route );

		if ( null !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}

		$request->set_query_params( $query );

		return rest_do_request( $request );
	}

	/**
	 * The response body as a client would decode it.
	 *
	 * @param WP_REST_Response $response
	 *
	 * @return mixed
	 */
	protected function json( WP_REST_Response $response ): mixed {
		return json_decode( wp_json_encode( rest_get_server()->response_to_data( $response, false ) ), true );
	}

	/**
	 * Assert a response's status and, for errors, its code.
	 *
	 * @param int              $status
	 * @param WP_REST_Response $response
	 * @param string|null      $code
	 *
	 * @return void
	 */
	protected function assertResponse( int $status, WP_REST_Response $response, ?string $code = null ): void {
		$this->assertSame( $status, $response->get_status(), wp_json_encode( $response->get_data() ) );

		if ( null !== $code ) {
			$this->assertSame( $code, $response->get_data()['code'] );
		}
	}

	/**
	 * Remove the route registration the plugin hooked at boot.
	 *
	 * @return void
	 */
	private function remove_boot_routes(): void {
		global $wp_filter;

		foreach ( $wp_filter['rest_api_init']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if (
					$callback['function'] instanceof \Closure &&
					CoreServiceProvider::class === ( new ReflectionFunction( $callback['function'] ) )->getClosureScopeClass()?->getName()
				) {
					remove_action( 'rest_api_init', $callback['function'], $priority );
				}
			}
		}
	}
}
