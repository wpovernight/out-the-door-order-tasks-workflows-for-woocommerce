<?php

namespace WPO\OTD\Services;

use WPO\OTD\Emails\TaskCreatedEmail;
use WPO\OTD\Emails\TaskUpdatedEmail;

defined( 'ABSPATH' ) || exit;

final class EmailService {
	private readonly TaskManagerService $task_manager_service;

	/**
	 * Constructor.
	 *
	 * @param TaskManagerService $task_manager_service
	 */
	public function __construct( TaskManagerService $task_manager_service ) {
		$this->task_manager_service = $task_manager_service;
	}

	/**
	 * Register hooks and filters.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Register custom email classes and actions with WooCommerce.
		add_filter( 'woocommerce_email_classes', array( $this, 'register_email_classes' ) );
		add_filter( 'woocommerce_email_actions', array( $this, 'register_email_actions' ) );

		// Add action to include task details in email templates.
		add_action( 'wpo_aom_email_task_details', array( $this, 'email_task_details' ), 10, 4 );
	}

	/**
	 * Register custom email classes with WooCommerce.
	 *
	 * @param array $email_classes Existing email classes.
	 *
	 * @return array Modified email classes.
	 */
	public function register_email_classes( array $email_classes ): array {
		$email_classes['WPO_AOM_Task_Created_Email'] = new TaskCreatedEmail();
		$email_classes['WPO_AOM_Task_Updated_Email'] = new TaskUpdatedEmail( $this->task_manager_service );

		return $email_classes;
	}

	/**
	 * Register custom email actions with WooCommerce mailer.
	 *
	 * @param array $actions Existing email actions.
	 *
	 * @return array Modified email actions.
	 */
	public function register_email_actions( array $actions ): array {
		$actions[] = 'wpo_aom_task_created';
		$actions[] = 'wpo_aom_task_updated';

		return $actions;
	}

	/**
	 * Output task details in email.
	 *
	 * @param array     $task_data     Task data array.
	 * @param bool      $sent_to_admin Is sent to admin.
	 * @param bool      $plain_text    Is plain text.
	 * @param \WC_Email $email         Email object.
	 *
	 * @return void
	 */
	public function email_task_details(
		array $task_data, bool $sent_to_admin, bool $plain_text, \WC_Email $email
	): void {
		if ( $plain_text ) {
			wc_get_template(
				'emails/plain/email-task-details.php',
				array(
					'task_data'     => $task_data,
					'sent_to_admin' => $sent_to_admin,
					'plain_text'    => $plain_text,
					'email'         => $email,
					'email_service' => $this,
				),
				'',
				WPO_OTD()->plugin_path() . '/templates/'
			);
		} else {
			wc_get_template(
				'emails/email-task-details.php',
				array(
					'task_data'     => $task_data,
					'sent_to_admin' => $sent_to_admin,
					'plain_text'    => $plain_text,
					'email'         => $email,
					'email_service' => $this,
				),
				'',
				WPO_OTD()->plugin_path() . '/templates/'
			);
		}
	}

	/**
	 * Format field value based on field type.
	 *
	 * @param string                $field_slug
	 * @param array|string|int|null $field_value
	 * @param array                 $field_object
	 *
	 * @return ?string Formatted value.
	 */
	public function format_by_field_type( string $field_slug, $field_value, array $field_object ): ?string {
		/**
		 * Filter the map of field types to the field slugs handled by each formatter.
		 *
		 * @param array $field_types Map of field type => list of field slugs.
		 */
		$field_types = apply_filters(
			'wpo_aom_email_task_updated_field_types',
			array(
				'select' => array( 'status', 'priority' ),
				'date'   => array( 'due_date', 'done_date', 'archived_date' ),
				'user'   => array( 'creator' ),
				'number' => array( 'order' ),
			)
		);

		// Handle select fields.
		if ( in_array( $field_slug, $field_types['select'], true ) ) {
			return $this->format_select_field( $field_value, $field_object );
		}

		// Handle date fields.
		if ( in_array( $field_slug, $field_types['date'], true ) ) {
			return $this->format_date_field( $field_value );
		}

		// Handle user fields.
		if ( in_array( $field_slug, $field_types['user'], true ) ) {
			return $this->format_user_field( $field_value );
		}

		// Handle number fields - special case for order field.
		if ( in_array( $field_slug, $field_types['number'], true ) ) {
			return $this->format_number_field( $field_value );
		}

		$value = is_array( $field_value ) && ! empty( $field_value ) ? $field_value[0] : $field_value;

		/**
		 * Allow custom formatting for other field types via filter.
		 *
		 * @param string|null $value        The formatted value (default: unformatted).
		 * @param string      $field_slug   The field slug.
		 * @param array       $field_object The complete field object.
		 */
		$value = apply_filters( 'wpo_aom_email_task_updated_field_value', $value, $field_slug, $field_object );

		return ! is_null( $value ) ? (string) $value : null;
	}

	/**
	 * Format select field value by getting option label.
	 *
	 * @param string|int|array $field_value Field value (option ID).
	 * @param array            $field_object Field object.
	 *
	 * @return string Formatted value.
	 */
	private function format_select_field( $field_value, array $field_object ): string {
		// Handle resolved format with label.
		if ( is_array( $field_value ) && isset( $field_value['resolved']['label'] ) ) {
			return (string) $field_value['resolved']['label'];
		}

		if ( empty( $field_object['options'] ) ) {
			return (string) ( is_array( $field_value ) ? reset( $field_value ) : $field_value );
		}

		// Handle array of IDs (from updated_fields).
		if ( is_array( $field_value ) ) {
			// Get first value from array (select fields typically have single value).
			$option_id = reset( $field_value );
			if ( empty( $option_id ) ) {
				return __( 'None', 'out-the-door-order-tasks-workflows-for-woocommerce' );
			}
			$field_value = $option_id;
		}

		// Look up option label by ID.
		foreach ( $field_object['options'] as $option ) {
			if ( (string) $option['id'] === (string) $field_value ) {
				return $option['label'];
			}
		}

		return (string) $field_value;
	}

	/**
	 * Format date field value.
	 *
	 * @param string|int|array $field_value Field value.
	 *
	 * @return string Formatted date.
	 */
	private function format_date_field( $field_value ): string {
		if ( empty( $field_value ) ) {
			return '';
		}

		// Handle resolved format.
		if ( is_array( $field_value ) && isset( $field_value['resolved'] ) ) {
			$timestamp = strtotime( $field_value['resolved'] );
			return false !== $timestamp ? date_i18n( get_option( 'date_format' ), $timestamp ) : '';
		}

		// Handle array format with 'raw' key.
		if ( is_array( $field_value ) && isset( $field_value['raw'] ) ) {
			if ( empty( $field_value['raw'] ) ) {
				return '';
			}
			$field_value = $field_value['raw'];
		}

		// Handle plain array (from updated_fields) - get first value.
		if ( is_array( $field_value ) ) {
			$date_value = reset( $field_value );
			if ( empty( $date_value ) ) {
				return '';
			}
			$field_value = $date_value;
		}

		// Convert to timestamp and format.
		$timestamp = strtotime( $field_value );

		if ( false === $timestamp ) {
			return '';
		}

		return date_i18n( get_option( 'date_format' ), $timestamp );
	}

	/**
	 * Format user field value by getting user display name.
	 *
	 * @param array|int $field_value User ID.
	 *
	 * @return string User display name or 'Unassigned'.
	 */
	private function format_user_field( $field_value ): string {
		if ( is_array( $field_value ) && isset( $field_value['resolved'] ) ) {
			return $field_value['resolved']['display_name']
			       ?? $field_value['resolved']['username']
			          ?? __( 'Unassigned', 'out-the-door-order-tasks-workflows-for-woocommerce' );
		}

		// Extract raw user ID from array format.
		if ( is_array( $field_value ) ) {
			$field_value = $field_value['raw'] ?? 0;
		}

		$user = get_userdata( absint( $field_value ) );

		return $user ? $user->display_name : __( 'Unassigned', 'out-the-door-order-tasks-workflows-for-woocommerce' );
	}

	/**
	 * Format number field value.
	 *
	 * @param string|int|array $field_value Field value.
	 *
	 * @return string Formatted number.
	 */
	private function format_number_field( $field_value ): string {
		if ( is_array( $field_value ) ) {
			// Handle resolved format with raw value.
			if ( isset( $field_value['raw'] ) ) {
				$raw_value = $field_value['raw'];
				// If raw value is still an array, process it.
				if ( is_array( $raw_value ) ) {
					return implode( ', ', array_filter( $raw_value ) );
				}
				return (string) $raw_value;
			}

			// Handle plain arrays.
			return implode( ', ', array_filter( $field_value ) );
		}

		return (string) $field_value;
	}
}
