<?php

namespace WPO\OTD\Tests\Unit\REST;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use stdClass;
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

	/**
	 * @dataProvider strict_rule_provider
	 */
	public function test_presence_blank_and_strict_integer_rules( string $rule, $value, bool $passes ): void {
		$errors = $this->controller->validate( array( 'field' => $value ), array( 'field' => $rule ) );

		$this->assertSame( $passes, array() === $errors, var_export( $errors, true ) );
	}

	public function strict_rule_provider(): array {
		return array(
			'present, zero'                  => array( 'present', 0, true ),
			'present, string zero'           => array( 'present', '0', true ),
			'present, empty string'          => array( 'present', '', true ),
			'present, false'                 => array( 'present', false, true ),
			'present, empty array'           => array( 'present', array(), true ),
			'present, negative'              => array( 'present', -1, true ),
			'present, null'                  => array( 'present', null, true ),
			'not null, zero'                 => array( 'not_null', 0, true ),
			'not null, string zero'          => array( 'not_null', '0', true ),
			'not null, empty string'         => array( 'not_null', '', true ),
			'not null, false'                => array( 'not_null', false, true ),
			'not null, null'                 => array( 'not_null', null, false ),
			'present strict integer, null'   => array( 'present|strict_integer', null, true ),
			'all three, null'                => array( 'present|not_null|strict_integer', null, false ),
			'all three, zero'                => array( 'present|not_null|strict_integer', 0, true ),
			'all three, numeric string'      => array( 'present|not_null|strict_integer', '10', false ),
			'not blank, string zero'         => array( 'not_blank', '0', true ),
			'not blank, padded text'         => array( 'not_blank', ' a ', true ),
			'not blank, empty string'        => array( 'not_blank', '', false ),
			'not blank, whitespace'          => array( 'not_blank', " \t\n", false ),
			'not blank, zero'                => array( 'not_blank', 0, true ),
			'not blank, false'               => array( 'not_blank', false, true ),
			'not blank, null'                => array( 'not_blank', null, true ),
			'strict integer, ten'            => array( 'strict_integer', 10, true ),
			'strict integer, zero'           => array( 'strict_integer', 0, true ),
			'strict integer, negative'       => array( 'strict_integer', -1, true ),
			'strict integer, null'           => array( 'strict_integer', null, true ),
			'strict integer, numeric string' => array( 'strict_integer', '10', false ),
			'strict integer, string zero'    => array( 'strict_integer', '0', false ),
			'strict integer, empty string'   => array( 'strict_integer', '', false ),
			'strict integer, true'           => array( 'strict_integer', true, false ),
			'strict integer, false'          => array( 'strict_integer', false, false ),
			'strict integer, whole float'    => array( 'strict_integer', 10.0, false ),
			'strict integer, float'          => array( 'strict_integer', 1.5, false ),
			'strict integer, array'          => array( 'strict_integer', array( 1 ), false ),
			'integer, numeric string'        => array( 'integer', '10', true ),
			'integer, padded string'         => array( 'integer', ' 10 ', true ),
			'integer, true'                  => array( 'integer', true, true ),
			'integer, whole float'           => array( 'integer', 10.0, true ),
			'integer, negative'              => array( 'integer', -1, true ),
			'required, zero'                 => array( 'required', 0, false ),
			'required, false'                => array( 'required', false, false ),
			'required, empty array'          => array( 'required', array(), false ),
			'required, negative'             => array( 'required', -1, true ),
		);
	}

	public function test_present_and_the_strict_rules_report_their_messages(): void {
		$this->assertSame(
			array(
				'missing' => array( 'The missing field is required.' ),
				'nulled'  => array( 'The nulled field may not be null.' ),
				'blank'   => array( 'The blank field may not be blank.' ),
				'count'   => array( 'The count field must be an integer.' ),
			),
			$this->controller->validate(
				array( 'nulled' => null, 'blank' => ' ', 'count' => '10' ),
				array(
					'missing' => 'present|not_null|strict_integer',
					'nulled'  => 'present|not_null|strict_integer',
					'blank'   => 'present|string|not_blank',
					'count'   => 'present|not_null|strict_integer',
				)
			)
		);
	}

	public function test_present_fails_only_on_an_absent_key(): void {
		$rules = array(
			'priority'    => 'present|not_null|strict_integer',
			'title'       => 'present|string|not_blank',
			'enabled'     => 'present|boolean',
			'description' => 'present',
		);

		$this->assertSame(
			array(),
			$this->controller->validate( array( 'priority' => 0, 'title' => '0', 'enabled' => false, 'description' => null ), $rules )
		);
		$this->assertSame(
			array(
				'priority'    => array( 'The priority field is required.' ),
				'title'       => array( 'The title field is required.' ),
				'enabled'     => array( 'The enabled field is required.' ),
				'description' => array( 'The description field is required.' ),
			),
			$this->controller->validate( array(), $rules ),
			'A missing key is reported once, not also as null.'
		);
	}

	public function test_not_null_alone_lets_a_missing_key_through(): void {
		$this->assertSame( array(), $this->controller->validate( array(), array( 'priority' => 'not_null|strict_integer' ) ) );
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

	public function test_a_route_that_keeps_its_rules_applies_each_one_a_filter_takes_away(): void {
		Filters\expectApplied( 'wpo_otd_rest_validation_rules' )->once()->andReturn( array() );

		$this->assertSame(
			array(
				'count' => array( 'The count field must be an integer.' ),
				'title' => array( 'The title field is required.' ),
			),
			$this->controller->validate(
				array( 'count' => '10' ),
				array(
					'count' => 'present|not_null|strict_integer',
					'title' => 'present|not_null|string',
				),
				true
			)
		);
	}

	public function test_a_filter_can_add_rules_to_a_route_that_keeps_its_own(): void {
		Filters\expectApplied( 'wpo_otd_rest_validation_rules' )->once()->andReturn( array( 'title' => 'string|max:3' ) );

		$this->assertSame(
			array( 'title' => array( 'The title field may not be longer than 3 characters.' ) ),
			$this->controller->validate( array( 'title' => 'Packed' ), array( 'title' => 'present|string' ), true )
		);
	}

	public function test_a_rule_a_filter_changes_on_a_route_that_keeps_its_rules_is_applied_after_those_left(): void {
		Filters\expectApplied( 'wpo_otd_rest_validation_rules' )->once()->andReturn( array( 'count' => 'integer|max:1' ) );

		$this->assertSame(
			array(
				'count' => array(
					'The count field may not be longer than 1 characters.',
					'The count field must be an integer.',
				),
			),
			$this->controller->validate( array( 'count' => '10' ), array( 'count' => 'present|not_null|strict_integer' ), true ),
			'The rules the filter left first; then the strict integer it took away, which "10" fails.'
		);
	}

	public function test_a_regex_a_filter_adds_in_another_delimiter_keeps_the_routes_rules(): void {
		Filters\expectApplied( 'wpo_otd_rest_validation_rules' )->twice()->andReturn( array( 'revision' => 'regex:#^[0-9|]+$#' ) );

		$this->assertSame(
			array( 'revision' => array( 'The revision field is required.' ) ),
			$this->controller->validate( array(), array( 'revision' => 'present|not_null|strict_integer' ), true ),
			'The filter\'s regex does not swallow the route\'s rules after it.'
		);
		$this->assertSame( array(), $this->controller->validate( array( 'revision' => 4 ), array( 'revision' => 'present|not_null|strict_integer' ), true ) );
	}

	public function test_a_rule_entry_a_filter_adds_that_is_not_a_string_is_ignored(): void {
		Filters\expectApplied( 'wpo_otd_rest_validation_rules' )->once()->andReturnUsing(
			static fn( array $rules ): array => $rules + array(
				'extra' => array( 'string' ),
				'count' => 5,
			)
		);

		$this->assertSame(
			array( 'title' => array( 'The title field must be a string.' ) ),
			$this->controller->validate( array( 'title' => 3, 'extra' => 7 ), array( 'title' => 'present|string' ), true )
		);
	}

	public function test_a_route_that_keeps_its_rules_ignores_a_filter_that_gives_no_rules_at_all(): void {
		Filters\expectApplied( 'wpo_otd_rest_validation_rules' )->once()->andReturn( 'none' );

		$this->assertSame(
			array( 'title' => array( 'The title field is required.' ) ),
			$this->controller->validate( array(), array( 'title' => 'present' ), true )
		);
	}

	public function test_a_pipe_inside_a_regex_survives_a_rule_put_back(): void {
		Filters\expectApplied( 'wpo_otd_rest_validation_rules' )->once()->andReturn( array( 'key' => 'string' ) );

		$this->assertSame(
			array( 'key' => array( 'The key field is invalid.' ) ),
			$this->controller->validate( array( 'key' => 'other' ), array( 'key' => 'string|regex:/^(draft|live)$/' ), true )
		);
	}

	public function test_a_route_that_does_not_keep_its_rules_lets_a_filter_take_them_away(): void {
		Filters\expectApplied( 'wpo_otd_rest_validation_rules' )->once()->andReturn( array() );

		$this->assertSame( array(), $this->controller->validate( array( 'count' => '10' ), array( 'count' => 'present|not_null|strict_integer' ) ) );
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

	public function test_a_route_that_is_always_paginated_reads_no_params_as_the_first_page(): void {
		$this->assertSame( array( 'page' => 1, 'per_page' => 20 ), $this->controller->pagination_params( $this->request( null, null ), 20, true ) );
		$this->assertSame( array( 'page' => 1, 'per_page' => 10 ), $this->controller->pagination_params( $this->request( null, null ), 10, true ) );
	}

	public function test_a_route_that_is_always_paginated_reads_its_params_as_any_other(): void {
		$this->assertSame( array( 'page' => 3, 'per_page' => 20 ), $this->controller->pagination_params( $this->request( 3, null ), 20, true ) );
		$this->assertSame( array( 'page' => 1, 'per_page' => BaseRestController::MAX_PER_PAGE ), $this->controller->pagination_params( $this->request( null, 500 ), 20, true ) );
	}

	public function test_a_page_holds_at_most_100_items(): void {
		$this->assertSame( 100, BaseRestController::MAX_PER_PAGE );
		$this->assertSame( array( 'page' => 1, 'per_page' => 100 ), $this->controller->pagination_params( $this->request( 1, 101 ) ) );
	}

	/** ================================
	 *   Values as they are sent
	 *  ================================ */

	public function test_an_empty_map_is_sent_as_an_object_and_any_other_as_it_is(): void {
		$this->assertInstanceOf( stdClass::class, ExposedRestController::map( array() ) );
		$this->assertSame( '{}', json_encode( ExposedRestController::map( array() ) ) );
		$this->assertSame( array( 'a' => 1 ), ExposedRestController::map( array( 'a' => 1 ) ) );
		$this->assertSame( array( 1, 2 ), ExposedRestController::map( array( 1, 2 ) ) );
	}

	/**
	 * @dataProvider utc_provider
	 */
	public function test_a_stored_utc_time_is_sent_in_iso_8601_with_a_z( ?string $stored, ?string $sent ): void {
		$this->assertSame( $sent, ExposedRestController::utc( $stored ) );
	}

	public function utc_provider(): array {
		return array(
			'a stored time'    => array( '2026-10-29 07:05:09', '2026-10-29T07:05:09Z' ),
			'midnight'         => array( '2026-01-01 00:00:00', '2026-01-01T00:00:00Z' ),
			'none'             => array( null, null ),
			'a date alone'     => array( '2026-10-29', null ),
			'ISO 8601 already' => array( '2026-10-29T07:05:09Z', null ),
			'not a time'       => array( 'soon', null ),
			'empty'            => array( '', null ),
			'a zero date'      => array( '0000-00-00 00:00:00', null ),
			'a day past month' => array( '2026-02-30 12:00:00', null ),
			'a time past day'  => array( '2026-02-28 25:61:61', null ),
			'a leap day'       => array( '2024-02-29 23:59:59', '2024-02-29T23:59:59Z' ),
		);
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

	public function validate( array $data, array $rules, bool $keep_rules = false ): array {
		return parent::validate( $data, $rules, $keep_rules );
	}

	public function pagination_params( WP_REST_Request $request, int $default_per_page = 10, bool $always = false ): ?array {
		return parent::pagination_params( $request, $default_per_page, $always );
	}

	public static function map( array $map ): array|stdClass {
		return parent::map( $map );
	}

	public static function utc( ?string $datetime ): ?string {
		return parent::utc( $datetime );
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
