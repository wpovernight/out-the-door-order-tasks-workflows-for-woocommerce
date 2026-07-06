<?php

namespace WPO\AOM\REST;

use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

abstract class BaseRestController {
	protected string $namespace = 'wc/v3/wpo/aom';

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
		return (bool) apply_filters( 'wpo_aom_rest_api_permissions_check', wc_rest_check_manager_permissions( 'settings', 'edit' ), $request );
	}

	/**
	 * Validates data against specified rules.
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
		$rules = apply_filters( 'wpo_aom_rest_validation_rules', $rules, $data );

		$errors = array();

		foreach ( $rules as $field => $rule_string ) {
			$rules_array = $this->parse_rules( $rule_string );
			$value       = $data[ $field ] ?? null;

			foreach ( $rules_array as $rule ) {
				// Handle rules with parameters (e.g., regex:/pattern/)
				[$rule_name, $rule_parameter] = explode( ':', $rule, 2 ) + array( null, null );

				$error = $this->validate_rule( $field, $value, $rule_name, $rule_parameter );

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
	 * @param string $rule_name The validation rule
	 * @param string|null $rule_parameter Optional parameter for the rule (e.g., regex pattern)
	 *
	 * @return string|null Error message or null if validation passes
	 */
	private function validate_rule( string $field, mixed $value, string $rule_name, ?string $rule_parameter ): ?string {
		switch ( $rule_name ) {
			case 'required':
				if ( empty( $value ) ) {
					return $this->format_error_message( $field, 'is required' );
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
