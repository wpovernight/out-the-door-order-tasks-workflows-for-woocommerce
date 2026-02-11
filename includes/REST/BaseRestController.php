<?php

namespace WPO\AOM\REST;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

abstract class BaseRestController {
	protected string $namespace = 'wc/v3/wpo/aom';

	abstract public function register_routes(): void;

	/**
	 * Registers the REST API routes when the REST API is initialized.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
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
		return apply_filters( 'wpo_aom_rest_api_permissions_check', wc_rest_check_manager_permissions( 'settings', 'edit' ), $request );
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
		$errors = array();

		foreach ( $rules as $field => $rule_string ) {
			$rules_array = explode( '|', $rule_string );
			$value       = $data[ $field ] ?? null;

			foreach ( $rules_array as $rule ) {
				$error = $this->validate_rule( $field, $value, $rule );
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
	 * @param string $rule The validation rule
	 *
	 * @return string|null Error message or null if validation passes
	 */
	private function validate_rule( string $field, $value, string $rule ): ?string {
		switch ( $rule ) {
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
				if ( ! is_null( $value ) && ! filter_var( $value, FILTER_VALIDATE_INT ) ) {
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
}
