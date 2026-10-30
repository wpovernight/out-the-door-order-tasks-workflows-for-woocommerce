<?php

namespace WPO\OTD\Tests\Integration\REST;

use WP_Error;
use WP_REST_Request;
use WPO\OTD\REST\BaseRestController;
use WPO\OTD\Tests\IntegrationTestCase;

/**
 * How the base controller reads a body and answers an input or server error, with WordPress's own requests.
 */
class BaseRestControllerTest extends IntegrationTestCase {
	private const REFUSED = 'Nothing was saved.';

	private BodyRestController $controller;

	public function set_up() {
		parent::set_up();

		$this->controller = new BodyRestController();
	}

	/** ================================
	 *   strict_json_object_body()
	 *  ================================ */

	public function test_a_json_object_is_read_as_its_members(): void {
		$this->assertSame( array( 'title' => 'Packed', 'count' => 2 ), $this->controller->strict_json_object_body( self::json( '{"title":"Packed","count":2}' ), self::REFUSED ) );
	}

	public function test_no_body_and_an_empty_object_are_read_as_no_members(): void {
		$this->assertSame( array(), $this->controller->strict_json_object_body( new WP_REST_Request( 'POST', '/x' ), self::REFUSED ) );
		$this->assertSame( array(), $this->controller->strict_json_object_body( self::json( '' ), self::REFUSED ) );
		$this->assertSame( array(), $this->controller->strict_json_object_body( self::json( '   ' ), self::REFUSED ) );
		$this->assertSame( array(), $this->controller->strict_json_object_body( self::json( '{}' ), self::REFUSED ) );
	}

	/**
	 * @dataProvider not_an_object_provider
	 */
	public function test_json_that_is_not_an_object_is_refused_as_a_whole( string $body ): void {
		$this->assert_invalid_data( array( '' => array( 'The body must be a JSON object.' ) ), self::REFUSED, $this->controller->strict_json_object_body( self::json( $body ), self::REFUSED ) );
	}

	public function not_an_object_provider(): array {
		return array(
			'an empty list' => array( '[]' ),
			'a list'        => array( '[1,2]' ),
			'a string'      => array( '"Packed"' ),
			'a number'      => array( '5' ),
			'null'          => array( 'null' ),
			'true'          => array( 'true' ),
		);
	}

	public function test_a_body_not_sent_as_json_is_refused_as_a_whole(): void {
		$form = new WP_REST_Request( 'POST', '/x' );
		$form->set_header( 'Content-Type', 'application/x-www-form-urlencoded' );
		$form->set_body( 'title=Packed' );
		$form->set_body_params( array( 'title' => 'Packed' ) );

		$text = new WP_REST_Request( 'POST', '/x' );
		$text->set_header( 'Content-Type', 'text/plain' );
		$text->set_body( '{"title":"Packed"}' );

		foreach ( array( $form, $text ) as $request ) {
			$this->assert_invalid_data( array( '' => array( 'Send the body as JSON.' ) ), self::REFUSED, $this->controller->strict_json_object_body( $request, self::REFUSED ) );
		}
	}

	public function test_form_params_alone_are_refused_too(): void {
		$request = new WP_REST_Request( 'POST', '/x' );
		$request->set_body_params( array( 'title' => 'Packed' ) );

		$this->assert_invalid_data( array( '' => array( 'Send the body as JSON.' ) ), self::REFUSED, $this->controller->strict_json_object_body( $request, self::REFUSED ) );
	}

	public function test_the_query_string_is_never_read_as_the_body(): void {
		$request = self::json( '{"title":"Packed"}' );
		$request->set_query_params( array( 'title' => 'Boxed', 'count' => '3' ) );

		$this->assertSame( array( 'title' => 'Packed' ), $this->controller->strict_json_object_body( $request, self::REFUSED ) );
	}

	/** ================================
	 *   refuse_body()
	 *  ================================ */

	public function test_a_request_with_no_body_or_an_empty_object_passes(): void {
		$this->assertNull( $this->controller->refuse_body( new WP_REST_Request( 'GET', '/x' ) ) );
		$this->assertNull( $this->controller->refuse_body( self::json( '{}' ) ) );
		$this->assertNull( $this->controller->refuse_body( self::json( '' ) ) );
	}

	public function test_the_query_string_is_no_body(): void {
		$request = new WP_REST_Request( 'GET', '/x' );
		$request->set_query_params( array( 'page' => '2' ) );

		$this->assertNull( $this->controller->refuse_body( $request ) );
	}

	public function test_each_json_key_is_refused_under_its_own_path(): void {
		$this->assert_invalid_data(
			array(
				'title' => array( 'This route takes no body.' ),
				'0'     => array( 'This route takes no body.' ),
			),
			'This route takes no body.',
			$this->controller->refuse_body( self::json( '{"title":"Packed","0":1}' ) )
		);
	}

	public function test_json_with_no_keys_to_name_is_refused_under_the_empty_path(): void {
		foreach ( array( '[]', '[1]', '"Packed"', 'null' ) as $body ) {
			$this->assert_invalid_data( array( '' => array( 'This route takes no body.' ) ), 'This route takes no body.', $this->controller->refuse_body( self::json( $body ) ) );
		}
	}

	/**
	 * @dataProvider form_body_provider
	 */
	public function test_a_form_body_is_refused_by_its_keys_whatever_the_method( string $method, ?string $content_type ): void {
		$request = new WP_REST_Request( $method, '/x' );

		if ( null !== $content_type ) {
			$request->set_header( 'Content-Type', $content_type );
		}

		$request->set_body( 'title=Packed&count=2' );

		$this->assert_invalid_data(
			array(
				'title' => array( 'This route takes no body.' ),
				'count' => array( 'This route takes no body.' ),
			),
			'This route takes no body.',
			$this->controller->refuse_body( $request )
		);
	}

	public function form_body_provider(): array {
		return array(
			'DELETE, form-encoded'     => array( 'DELETE', 'application/x-www-form-urlencoded' ),
			'GET, with no type'        => array( 'GET', null ),
			'POST, already parsed too' => array( 'POST', 'application/x-www-form-urlencoded' ),
		);
	}

	public function test_a_body_of_another_type_is_refused_as_a_whole(): void {
		$request = new WP_REST_Request( 'GET', '/x' );
		$request->set_header( 'Content-Type', 'text/plain' );
		$request->set_body( 'Packed' );

		$this->assert_invalid_data( array( '' => array( 'This route takes no body.' ) ), 'This route takes no body.', $this->controller->refuse_body( $request ) );
	}

	/** ================================
	 *   invalid_data() and server_error()
	 *  ================================ */

	public function test_an_input_error_sends_each_path_as_a_key_even_one_php_reads_as_a_number(): void {
		$error = $this->controller->invalid_data( array( '0' => array( 'Wrong.' ), 'title' => array( 'Missing.' ) ), self::REFUSED );

		$this->assert_invalid_data( array( '0' => array( 'Wrong.' ), 'title' => array( 'Missing.' ) ), self::REFUSED, $error );
		$this->assertSame( '{"0":["Wrong."],"title":["Missing."]}', wp_json_encode( $error->get_error_data()['errors'] ) );
		$this->assertSame( '{}', wp_json_encode( $this->controller->invalid_data( array(), self::REFUSED )->get_error_data()['errors'] ) );
	}

	public function test_a_server_error_logs_its_cause_and_never_sends_it(): void {
		$logged = array();
		add_filter(
			'wpo_otd_logging_enabled',
			static function ( $enabled, $level, $message ) use ( &$logged ) {
				$logged[] = "{$level}: {$message}";

				return false;
			},
			10,
			3
		);

		$error = $this->controller->server_error( 'save_failed', 'The status could not be saved.', 'Saving status 4 failed: MySQL error 1146' );

		$this->assertSame( 'save_failed', $error->get_error_code() );
		$this->assertSame( 'The status could not be saved.', $error->get_error_message() );
		$this->assertSame( array( 'status' => 500 ), $error->get_error_data() );
		$this->assertSame( array( 'error: Saving status 4 failed: MySQL error 1146' ), $logged );
	}

	/**
	 * A request with a JSON body.
	 *
	 * @param string $body
	 *
	 * @return WP_REST_Request
	 */
	private static function json( string $body ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/x' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( $body );

		return $request;
	}

	/**
	 * Assert an answer is an input error with these paths and messages.
	 *
	 * @param array<string, string[]> $errors
	 * @param string                  $message
	 * @param mixed                   $answer
	 *
	 * @return void
	 */
	private function assert_invalid_data( array $errors, string $message, mixed $answer ): void {
		$this->assertInstanceOf( WP_Error::class, $answer );
		$this->assertSame( 'invalid_data', $answer->get_error_code() );
		$this->assertSame( $message, $answer->get_error_message() );
		$this->assertSame( 400, $answer->get_error_data()['status'] );
		$this->assertIsObject( $answer->get_error_data()['errors'] );
		$this->assertSame( $errors, (array) $answer->get_error_data()['errors'] );
	}
}

/**
 * Makes the body and error helpers callable.
 */
class BodyRestController extends BaseRestController {
	public function register_routes(): void {
	}

	public function strict_json_object_body( WP_REST_Request $request, string $message ): array|WP_Error {
		return parent::strict_json_object_body( $request, $message );
	}

	public function refuse_body( WP_REST_Request $request ): ?WP_Error {
		return parent::refuse_body( $request );
	}

	public function invalid_data( array $errors, string $message ): WP_Error {
		return parent::invalid_data( $errors, $message );
	}

	public function server_error( string $code, string $message, string $cause ): WP_Error {
		return parent::server_error( $code, $message, $cause );
	}
}
