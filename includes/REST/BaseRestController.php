<?php

namespace WPO\OTD\REST;

use stdClass;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPO\OTD\Core\Logger;
use WPO\OTD\Traits\FormatsRestValues;
use WPO\OTD\Utilities\Paginator;

defined( 'ABSPATH' ) || exit;

abstract class BaseRestController {
	use FormatsRestValues;

	/**
	 * The most items a page holds: a larger per_page is read as this.
	 */
	public const MAX_PER_PAGE = 100;

	protected string $namespace = 'wc/v3/wpo/otd';

	abstract public function register_routes(): void;

	/**
	 * Build the standard success envelope: { data } (+ meta when provided).
	 *
	 * @param mixed                    $data The payload.
	 * @param array<string,mixed>|null $meta Optional metadata to attach alongside the data.
	 *
	 * @return WP_REST_Response
	 */
	protected function respond( mixed $data, ?array $meta = null ): WP_REST_Response {
		$payload = array( 'data' => $data );

		if ( null !== $meta ) {
			$payload['meta'] = $meta;
		}

		return rest_ensure_response( $payload );
	}

	/**
	 * Build the REST `meta` block from a Paginator.
	 *
	 * @param Paginator $paginator
	 *
	 * @return array<string,int|null>
	 */
	protected function pagination_meta( Paginator $paginator ): array {
		return array(
			'current_page' => $paginator->current_page(),
			'per_page'     => $paginator->per_page(),
			'last_page'    => $paginator->last_page(),
			'total'        => $paginator->total(),
			'from'         => $paginator->from(),
			'to'           => $paginator->to(),
		);
	}

	/**
	 * Respond with a page of already-formatted data, attaching pagination meta
	 * only when pagination was actually requested (i.e. $pagination is not null).
	 *
	 * @param array<int,mixed>         $data       Formatted items for the current page.
	 * @param Paginator                $paginator  Source of the meta block.
	 * @param array{page:int,per_page:int}|null $pagination Result of pagination_params(): null = full list, no meta.
	 *
	 * @return WP_REST_Response
	 */
	protected function respond_paginated( array $data, Paginator $paginator, ?array $pagination ): WP_REST_Response {
		return $this->respond(
			array_values( $data ),
			null === $pagination ? null : $this->pagination_meta( $paginator )
		);
	}

	/**
	 * Read a parameter of the route itself, such as the ID in /tasks/(?P<id>[\d]+).
	 *
	 * get_param() would let the body or the query string override it: PUT /tasks/5 with {"id": 6}
	 * would update task 6.
	 *
	 * @param WP_REST_Request $request
	 * @param string          $name
	 *
	 * @return string|null Null when the route has no such parameter.
	 */
	protected function url_param( WP_REST_Request $request, string $name ): ?string {
		$params = $request->get_url_params();

		return isset( $params[ $name ] ) ? (string) $params[ $name ] : null;
	}

	/**
	 * Read the request's JSON body, which must be a JSON object.
	 *
	 * A missing or empty body, a body that is not sent as JSON, and a JSON scalar or non-empty list are refused, since
	 * the handlers read named members from the body. Malformed JSON never gets here: WordPress refuses it first.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return array<string, mixed>|WP_Error The body's members, or a 400 invalid_body error.
	 */
	protected function json_object_body( WP_REST_Request $request ): array|WP_Error {
		$data = $request->get_json_params();

		if ( ! is_array( $data ) || ( array() !== $data && array_is_list( $data ) ) ) {
			return new WP_Error(
				'invalid_body',
				__( 'The request body must be a JSON object.', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
				array( 'status' => 400 )
			);
		}

		return $data;
	}

	/**
	 * Read the request's JSON body strictly: no body at all is an object with no members, and anything else must be a
	 * JSON object sent as JSON.
	 *
	 * Unlike json_object_body(), an empty list ([]) and a body not sent as JSON (form-encoded, say) are refused, so
	 * the body is read the same way whatever its Content-Type, and never from the query string. Malformed JSON never
	 * gets here: WordPress refuses it first.
	 *
	 * @param WP_REST_Request $request
	 * @param string          $message What the refusal says about the request as a whole.
	 *
	 * @return array<string, mixed>|WP_Error The body's members, or invalid_data() with the problem under "".
	 */
	protected function strict_json_object_body( WP_REST_Request $request, string $message ): array|WP_Error {
		$json = $this->json_object( $request );

		if ( false === $json ) {
			return $this->invalid_data( array( '' => array( 'The body must be a JSON object.' ) ), $message );
		}

		if ( null === $json && ( '' !== trim( (string) $request->get_body() ) || array() !== $request->get_body_params() ) ) {
			return $this->invalid_data( array( '' => array( 'Send the body as JSON.' ) ), $message );
		}

		return $json ?? array();
	}

	/**
	 * Refuse any body on a route that takes none: each key, JSON or form-encoded, under its own path, and a body with
	 * no keys to name under "". An empty JSON object passes; the query string is never a body.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|null invalid_data(); null when there is no body.
	 */
	protected function refuse_body( WP_REST_Request $request ): ?WP_Error {
		$json = $this->json_object( $request );
		$form = $request->get_body_params();

		// WordPress parses a form body of a method other than POST only once a parameter is read, which nothing may have
		// done yet on a route with no args, so it is parsed here as WordPress would: form-encoded, or with no
		// Content-Type at all.
		if ( null === $json && array() === $form && self::may_be_form( $request ) ) {
			parse_str( (string) $request->get_body(), $form );
		}

		$keys = array_merge( is_array( $json ) ? array_keys( $json ) : array(), array_keys( $form ) );

		if ( array() === $keys && ( false === $json || ( null === $json && '' !== trim( (string) $request->get_body() ) ) ) ) {
			$keys = array( '' );
		}

		if ( array() === $keys ) {
			return null;
		}

		$errors = array();

		foreach ( $keys as $key ) {
			$errors[ (string) $key ] = array( 'This route takes no body.' );
		}

		return $this->invalid_data( $errors, __( 'This route takes no body.', 'out-the-door-order-tasks-workflows-for-woocommerce' ) );
	}

	/**
	 * An input error: the key paths and what is wrong at each.
	 *
	 * @param array<string, string[]> $errors
	 * @param string                  $message What the error says about the request as a whole.
	 *
	 * @return WP_Error invalid_data, 400.
	 */
	protected function invalid_data( array $errors, string $message ): WP_Error {
		return new WP_Error(
			'invalid_data',
			$message,
			array(
				'status' => 400,
				// An object, so a key PHP reads as a number, such as "0", is still sent as a key.
				'errors' => (object) $errors,
			)
		);
	}

	/**
	 * A server error: a generic message, the cause logged and never sent.
	 *
	 * @param string $code
	 * @param string $message
	 * @param string $cause   What went wrong, for the log. Never a request or response body, or a query's text.
	 *
	 * @return WP_Error 500.
	 */
	protected function server_error( string $code, string $message, string $cause ): WP_Error {
		Logger::error( $cause );

		return new WP_Error( $code, $message, array( 'status' => 500 ) );
	}

	/**
	 * Check whether WordPress would read a request's body as form-encoded.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool True when it is sent form-encoded or with no Content-Type.
	 */
	private static function may_be_form( WP_REST_Request $request ): bool {
		$content_type = $request->get_content_type();

		return null === $content_type || 'application/x-www-form-urlencoded' === $content_type['value'];
	}

	/**
	 * The JSON body, if the request has one.
	 *
	 * WordPress has already refused JSON that does not decode, before the route runs.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return array|false|null The object's keys and values; false for JSON that is not an object (a list, a scalar
	 *                          or null); null when the body is not sent as JSON, or is empty.
	 */
	private function json_object( WP_REST_Request $request ): array|false|null {
		$body = (string) $request->get_body();

		if ( ! $request->is_json_content_type() || '' === trim( $body ) ) {
			return null;
		}

		// Decoded as objects too: decoded to arrays, {} and [] would look the same.
		if ( ! json_decode( $body ) instanceof stdClass ) {
			return false;
		}

		$json = $request->get_json_params();

		return is_array( $json ) ? $json : false;
	}

	/**
	 * Read pagination params from the request.
	 *
	 * @param WP_REST_Request $request
	 * @param int             $default_per_page
	 * @param bool            $always           Whether the route always sends a page, so a request with neither
	 *                                          param is the first page at the default size, not the full list.
	 *
	 * @return array{page:int,per_page:int}|null Null for the full list, never when $always.
	 */
	protected function pagination_params( WP_REST_Request $request, int $default_per_page = 10, bool $always = false ): ?array {
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		if ( null === $page && null === $per_page && ! $always ) {
			return null;
		}

		return array(
			'page'     => max( 1, (int) $page ),
			'per_page' => min( self::MAX_PER_PAGE, max( 1, (int) $per_page ?: $default_per_page ) ),
		);
	}

	/**
	 * Standard `page` / `per_page` route args for opt-in pagination.
	 *
	 * NOTE: No defaults are set, so an absent param signals "return the full list".
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function pagination_args(): array {
		return array(
			'page'     => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn( $value ) => (int) $value >= 1,
			),
			'per_page' => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				// Only enforce the lower bound here; the upper bound is clamped
				// to 100 in pagination_params() rather than rejected.
				'validate_callback' => static fn( $value ) => (int) $value >= 1,
			),
		);
	}

	/**
	 * Checks if the current user has permission to access the endpoint.
	 *
	 * @param WP_REST_Request $request The REST request
	 *
	 * @return bool True if the user has permission, false otherwise
	 */
	public function check_permissions( WP_REST_Request $request ): bool {
		/**
		 * Filter to modify the permission check for the REST API endpoint.
		 *
		 * @param bool $has_permission Whether the user has permission
		 * @param WP_REST_Request $request The REST request
		 *
		 * @return bool Modified permission check result
		 */
		return (bool) apply_filters( 'wpo_otd_rest_api_permissions_check', wc_rest_check_manager_permissions( 'settings', 'edit' ), $request );
	}

	/**
	 * Validates data against specified rules.
	 *
	 * Rules are pipe-separated, e.g. 'present|not_null|strict_integer'. A missing
	 * key is read as null, and every rule except "required" and "present" passes
	 * a missing key, so a type rule alone means "optional, but this type when
	 * given". The type rules also pass an explicit null, so 'present|strict_integer'
	 * accepts null; add not_null to refuse it.
	 *
	 * - required:       fails on anything empty() treats as empty: a missing key,
	 *                   null, '', '0', 0, 0.0, false and an empty array.
	 * - present:        fails only on a missing key. null, 0, '0', '', false and
	 *                   an empty array all pass.
	 * - not_null:       fails on a key that is given as null. A missing key passes,
	 *                   so 'not_null' alone means "optional, but never null" and
	 *                   'present|not_null' reports a missing key only once.
	 * - not_blank:      fails on a string that is empty or only whitespace. '0'
	 *                   passes; non-string values are left to the type rules.
	 * - string:         must be a string.
	 * - integer:        lenient: anything FILTER_VALIDATE_INT accepts, so '10',
	 *                   ' 10 ' and true pass.
	 * - strict_integer: must be a PHP int, as a JSON integer decodes to. Numeric
	 *                   strings, booleans and every float fail, 10.0 included,
	 *                   since JSON decodes 10.0 to a float.
	 * - boolean:        true, false, 0, 1, '0' or '1'.
	 * - array:          must be an array.
	 * - regex:/p/:      a string must match the pattern.
	 * - max:n:          a string may hold at most n characters.
	 *
	 * Unknown rule names are ignored.
	 *
	 * @param array $data The data to validate
	 * @param array $rules The validation rules
	 *
	 * @return array Validation errors, if any
	 */
	protected function validate( array $data, array $rules ): array {
		/**
		 * Filter validation rules before they are applied.
		 *
		 * @param array $rules The validation rules.
		 * @param array $data  The data being validated.
		 */
		$rules = apply_filters( 'wpo_otd_rest_validation_rules', $rules, $data );

		$errors = array();

		foreach ( $rules as $field => $rule_string ) {
			$rules_array = $this->parse_rules( $rule_string );
			$value       = $data[ $field ] ?? null;
			$is_present  = array_key_exists( $field, $data );

			foreach ( $rules_array as $rule ) {
				// Handle rules with parameters (e.g., regex:/pattern/)
				[$rule_name, $rule_parameter] = explode( ':', $rule, 2 ) + array( null, null );

				$error = $this->validate_rule( $field, $value, $is_present, $rule_name, $rule_parameter );

				if ( $error ) {
					$errors[ $field ][] = $error;
				}
			}
		}

		return $errors;
	}

	/**
	 * Validates a single rule against a value.
	 *
	 * @param string $field The field name
	 * @param mixed $value The value to validate
	 * @param bool $is_present Whether the field's key is in the data
	 * @param string $rule_name The validation rule
	 * @param string|null $rule_parameter Optional parameter for the rule (e.g., regex pattern)
	 *
	 * @return string|null Error message or null if validation passes
	 */
	private function validate_rule( string $field, mixed $value, bool $is_present, string $rule_name, ?string $rule_parameter ): ?string {
		switch ( $rule_name ) {
			case 'required':
				if ( empty( $value ) ) {
					return $this->format_error_message( $field, 'is required' );
				}
				break;
			case 'present':
				if ( ! $is_present ) {
					return $this->format_error_message( $field, 'is required' );
				}
				break;
			case 'not_null':
				if ( $is_present && is_null( $value ) ) {
					return $this->format_error_message( $field, 'may not be null' );
				}
				break;
			case 'not_blank':
				if ( is_string( $value ) && '' === trim( $value ) ) {
					return $this->format_error_message( $field, 'may not be blank' );
				}
				break;
			case 'string':
				if ( ! is_null( $value ) && ! is_string( $value ) ) {
					return $this->format_error_message( $field, 'must be a string' );
				}
				break;
			case 'integer':
				if ( ! is_null( $value ) && false === filter_var( $value, FILTER_VALIDATE_INT ) ) {
					return $this->format_error_message( $field, 'must be an integer' );
				}
				break;
			case 'strict_integer':
				if ( ! is_null( $value ) && ! is_int( $value ) ) {
					return $this->format_error_message( $field, 'must be an integer' );
				}
				break;
			case 'boolean':
				if (
					! is_null( $value ) &&
					! is_bool( $value ) &&
					! in_array( $value, array( 0, 1, '0', '1' ), true )
				) {
					return $this->format_error_message( $field, 'must be a boolean' );
				}
				break;
			case 'regex':
				if ( is_string( $value ) && ! empty( $rule_parameter ) && ! preg_match( $rule_parameter, $value ) ) {
					return $this->format_error_message( $field, 'is invalid' );
				}
				break;
			case 'array':
				if ( ! is_null( $value ) && ! is_array( $value ) ) {
					return $this->format_error_message( $field, 'must be an array' );
				}
				break;
			case 'max':
				if (
					is_string( $value ) &&
					is_numeric( $rule_parameter ) &&
					mb_strlen( $value ) > (int) $rule_parameter
				) {
					return $this->format_error_message(
						$field,
						sprintf( 'may not be longer than %d characters', (int) $rule_parameter )
					);
				}
				break;
		}

		return null;
	}

	/**
	 * Formats an error message for a field.
	 *
	 * @param string $field The field name
	 * @param string $message The error message
	 *
	 * @return string The formatted error message
	 */
	private function format_error_message( string $field, string $message ): string {
		return "The {$field} field {$message}.";
	}

	/**
	 * Parse a rule string into individual rules, respecting regex delimiters.
	 *
	 * Splits on '|' but not when inside a regex pattern (e.g., regex:/^[a-z0-9_-]+$/i).
	 *
	 * @param string $rule_string The pipe-separated rule string.
	 *
	 * @return array Array of individual rule strings.
	 */
	private function parse_rules( string $rule_string ): array {
		$rules        = array();
		$current_rule = '';
		$in_regex     = false;
		$regex_start  = -1;

		for ( $i = 0, $len = strlen( $rule_string ); $i < $len; $i ++ ) {
			$char = $rule_string[ $i ];

			// Detect start of regex pattern and record opening delimiter position.
			if ( ! $in_regex && 'regex:' === substr( $rule_string, $i, 6 ) ) {
				$in_regex    = true;
				$regex_start = $i + 6;
			}

			if ( '|' === $char && ! $in_regex ) {
				$rules[]      = $current_rule;
				$current_rule = '';
				continue;
			}

			$current_rule .= $char;

			// Detect end of regex pattern: closing delimiter (must be past the opening one).
			if ( $in_regex && $i > $regex_start && '/' === $char ) {
				// Skip past any trailing regex flags (e.g., 'i', 'm', 's').
				while ( $i + 1 < $len && ctype_alpha( $rule_string[ $i + 1 ] ) ) {
					$i++;
					$current_rule .= $rule_string[ $i ];
				}
				$in_regex = false;
			}
		}

		if ( '' !== $current_rule ) {
			$rules[] = $current_rule;
		}

		return $rules;
	}
}
