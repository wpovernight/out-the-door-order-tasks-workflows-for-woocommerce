<?php

namespace WPO\OTD\Tests\Unit\REST;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use WP_REST_Request;
use WP_REST_Response;
use WPO\OTD\REST\BaseRestController;
use WPO\OTD\Tests\TestCase;
use WPO\OTD\Utilities\Paginator;

class BaseRestControllerTest extends TestCase {
	private ExposedRestController $controller;

	protected function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WP_REST_Response' ) ) {
			// phpcs:ignore Squiz.PHP.Eval.Discouraged -- A minimal stand-in for the WordPress class.
			eval( 'class WP_REST_Response { public $data; public function __construct( $data = null ) { $this->data = $data; } }' );
		}

		Functions\when( 'rest_ensure_response' )->alias( static fn( $data ) => new WP_REST_Response( $data ) );

		$this->controller = new ExposedRestController();
	}

	/** ================================
	 *   validate()
	 *  ================================ */

	public function test_valid_data_has_no_errors(): void {
		$errors = $this->controller->validate(
			array(
				'label'   => 'Packed',
				'count'   => '12',
				'enabled' => '1',
				'color'   => '#ABCDEF',
				'items'   => array( 1 ),
			),
			array(
				'label'   => 'required|string|max:10',
				'count'   => 'integer',
				'enabled' => 'boolean',
				'color'   => 'string|regex:/^#([a-f0-9]{3}|[a-f0-9]{6})$/i',
				'items'   => 'array',
			)
		);

		$this->assertSame( array(), $errors );
	}

	/**
	 * @dataProvider failing_rule_provider
	 */
	public function test_each_rule_reports_its_own_message( $value, string $rules, array $expected ): void {
		$this->assertSame( $expected, $this->controller->validate( array( 'field' => $value ), array( 'field' => $rules ) ) );
	}

	public function failing_rule_provider(): array {
		return array(
			'required, missing'      => array( null, 'required', array( 'field' => array( 'The field field is required.' ) ) ),
			'required, empty string' => array( '', 'required', array( 'field' => array( 'The field field is required.' ) ) ),
			'string'                 => array( 12, 'string', array( 'field' => array( 'The field field must be a string.' ) ) ),
			'integer'                => array( '1.5', 'integer', array( 'field' => array( 'The field field must be an integer.' ) ) ),
			'boolean'                => array( 'yes', 'boolean', array( 'field' => array( 'The field field must be a boolean.' ) ) ),
			'array'                  => array( 'a,b', 'array', array( 'field' => array( 'The field field must be an array.' ) ) ),
			'regex'                  => array( 'ABC', 'regex:/^[a-z]+$/', array( 'field' => array( 'The field field is invalid.' ) ) ),
			'max'                    => array( 'Hello!', 'max:5', array( 'field' => array( 'The field field may not be longer than 5 characters.' ) ) ),
			'several rules'          => array( 7, 'string|max:3|integer', array( 'field' => array( 'The field field must be a string.' ) ) ),
		);
	}

	/**
	 * @dataProvider passing_rule_provider
	 */
	public function test_rules_accept_their_valid_values( $value, string $rules ): void {
		$this->assertSame( array(), $this->controller->validate( array( 'field' => $value ), array( 'field' => $rules ) ) );
	}

	public function passing_rule_provider(): array {
		return array(
			'optional rules skip a missing value' => array( null, 'string|integer|boolean|array|max:1|regex:/^x$/' ),
			'integer as a string'                 => array( '-3', 'integer' ),
			'boolean true'                        => array( true, 'boolean' ),
			'boolean zero'                        => array( 0, 'boolean' ),
			'boolean string one'                  => array( '1', 'boolean' ),
			'max counts characters, not bytes'    => array( 'ééééé', 'max:5' ),
			'max only checks strings'             => array( 123456, 'max:3' ),
			'regex only checks strings'           => array( 5, 'regex:/^[a-z]+$/' ),
			'unknown rules are ignored'           => array( 'x', 'uuid' ),
		);
	}

	public function test_required_rejects_the_string_zero(): void {
		// empty( '0' ) is true, so "required" cannot be satisfied by '0'.
		$this->assertArrayHasKey( 'field', $this->controller->validate( array( 'field' => '0' ), array( 'field' => 'required' ) ) );
	}

	public function test_a_pipe_inside_a_regex_does_not_split_the_rule(): void {
		$rules = array( 'key' => 'string|regex:/^(draft|live)$/i|max:5' );

		$this->assertSame( array(), $this->controller->validate( array( 'key' => 'LIVE' ), $rules ) );
		$this->assertSame(
			array( 'key' => array( 'The key field is invalid.' ) ),
			$this->controller->validate( array( 'key' => 'other' ), $rules )
		);
		$this->assertSame(
			array( 'key' => array( 'The key field is invalid.', 'The key field may not be longer than 5 characters.' ) ),
			$this->controller->validate( array( 'key' => 'archived' ), $rules ),
			'The rule after the regex still runs.'
		);
	}

	public function test_errors_are_collected_per_field(): void {
		$errors = $this->controller->validate(
			array( 'title' => '', 'description' => 3 ),
			array(
				'title'       => 'required|string',
				'description' => 'string',
			)
		);

		$this->assertSame(
			array(
				'title'       => array( 'The title field is required.' ),
				'description' => array( 'The description field must be a string.' ),
			),
			$errors
		);
	}

	public function test_rules_can_be_filtered(): void {
		Filters\expectApplied( 'wpo_otd_rest_validation_rules' )
			->once()
			->with( array( 'title' => 'string' ), array( 'title' => '' ) )
			->andReturn( array( 'title' => 'required' ) );

		$this->assertSame(
			array( 'title' => array( 'The title field is required.' ) ),
			$this->controller->validate( array( 'title' => '' ), array( 'title' => 'string' ) )
		);
	}

	/** ================================
	 *   Pagination and responses
	 *  ================================ */

	public function test_no_pagination_params_means_the_full_list(): void {
		$this->assertNull( $this->controller->pagination_params( $this->request( null, null ) ) );
	}

	/**
	 * @dataProvider pagination_provider
	 */
	public function test_pagination_params_are_clamped( $page, $per_page, array $expected ): void {
		$this->assertSame( $expected, $this->controller->pagination_params( $this->request( $page, $per_page ) ) );
	}

	public function pagination_provider(): array {
		return array(
			'both given'               => array( 3, 20, array( 'page' => 3, 'per_page' => 20 ) ),
			'page only uses default'   => array( 2, null, array( 'page' => 2, 'per_page' => 10 ) ),
			'per page only'            => array( null, 5, array( 'page' => 1, 'per_page' => 5 ) ),
			'per page capped at 100'   => array( 1, 500, array( 'page' => 1, 'per_page' => 100 ) ),
			'zero per page is default' => array( 1, 0, array( 'page' => 1, 'per_page' => 10 ) ),
			'page floor of one'        => array( -4, 10, array( 'page' => 1, 'per_page' => 10 ) ),
		);
	}

	public function test_route_parameters_come_from_the_url_only(): void {
		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_url_params' )->andReturn( array( 'id' => '5', 'field_id' => 1 ) );
		$request->shouldNotReceive( 'get_param' );

		$this->assertSame( '5', $this->controller->url_param( $request, 'id' ) );
		$this->assertSame( '1', $this->controller->url_param( $request, 'field_id' ) );
		$this->assertNull( $this->controller->url_param( $request, 'option_id' ) );
	}

	public function test_the_default_per_page_can_be_changed(): void {
		$this->assertSame( array( 'page' => 1, 'per_page' => 25 ), $this->controller->pagination_params( $this->request( 1, null ), 25 ) );
	}

	public function test_pagination_args_only_accept_positive_integers(): void {
		$args = $this->controller->pagination_args();

		foreach ( array( 'page', 'per_page' ) as $name ) {
			$this->assertSame( 'integer', $args[ $name ]['type'] );
			$this->assertSame( 'absint', $args[ $name ]['sanitize_callback'] );
			$this->assertArrayNotHasKey( 'default', $args[ $name ], 'An absent param must mean "no pagination".' );
			$this->assertTrue( $args[ $name ]['validate_callback']( '1' ) );
			$this->assertFalse( $args[ $name ]['validate_callback']( '0' ) );
		}
	}

	public function test_respond_wraps_data_and_optional_meta(): void {
		$this->assertSame( array( 'data' => array( 'a' ) ), $this->controller->respond( array( 'a' ) )->data );
		$this->assertSame( array( 'data' => 'a', 'meta' => array( 'x' => 1 ) ), $this->controller->respond( 'a', array( 'x' => 1 ) )->data );
	}

	public function test_respond_paginated_adds_meta_only_when_pagination_was_requested(): void {
		$paginator = new Paginator( array( 'c', 'd' ), 4, 2, 2 );

		$this->assertSame( array( 'data' => array( 'c', 'd' ) ), $this->controller->respond_paginated( array( 3 => 'c', 4 => 'd' ), $paginator, null )->data );
		$this->assertSame(
			array(
				'data' => array( 'c', 'd' ),
				'meta' => array(
					'current_page' => 2,
					'per_page'     => 2,
					'last_page'    => 2,
					'total'        => 4,
					'from'         => 3,
					'to'           => 4,
				),
			),
			$this->controller->respond_paginated( array( 'c', 'd' ), $paginator, array( 'page' => 2, 'per_page' => 2 ) )->data
		);
	}

	/** ================================
	 *   Permissions
	 *  ================================ */

	public function test_permission_follows_woocommerce_settings_capability(): void {
		$request = $this->request( null, null );

		Functions\expect( 'wc_rest_check_manager_permissions' )->twice()->with( 'settings', 'edit' )->andReturn( true, false );

		$this->assertTrue( $this->controller->check_permissions( $request ) );
		$this->assertFalse( $this->controller->check_permissions( $request ) );
	}

	public function test_permission_can_be_filtered(): void {
		$request = $this->request( null, null );

		Functions\when( 'wc_rest_check_manager_permissions' )->justReturn( false );
		Filters\expectApplied( 'wpo_otd_rest_api_permissions_check' )->once()->with( false, $request )->andReturn( 1 );

		$this->assertTrue( $this->controller->check_permissions( $request ) );
	}

	/**
	 * A request mock answering page and per_page.
	 *
	 * @param mixed $page
	 * @param mixed $per_page
	 *
	 * @return Mockery\MockInterface
	 */
	private function request( $page, $per_page ) {
		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->with( 'page' )->andReturn( $page );
		$request->shouldReceive( 'get_param' )->with( 'per_page' )->andReturn( $per_page );

		return $request;
	}
}

/**
 * Makes the protected helpers callable.
 */
class ExposedRestController extends BaseRestController {
	public function register_routes(): void {
	}

	public function validate( array $data, array $rules ): array {
		return parent::validate( $data, $rules );
	}

	public function pagination_params( WP_REST_Request $request, int $default_per_page = 10 ): ?array {
		return parent::pagination_params( $request, $default_per_page );
	}

	public function pagination_args(): array {
		return parent::pagination_args();
	}

	public function url_param( WP_REST_Request $request, string $name ): ?string {
		return parent::url_param( $request, $name );
	}

	public function respond( mixed $data, ?array $meta = null ): WP_REST_Response {
		return parent::respond( $data, $meta );
	}

	public function respond_paginated( array $data, Paginator $paginator, ?array $pagination ): WP_REST_Response {
		return parent::respond_paginated( $data, $paginator, $pagination );
	}
}
