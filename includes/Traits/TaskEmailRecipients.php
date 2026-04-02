<?php

namespace WPO\AOM\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * @mixin \WC_Email
 */
trait TaskEmailRecipients {
	/**
	 * Get recipients based on task data and settings.
	 *
	 * @param array $task_with_fields Complete task data.
	 *
	 * @return array
	 */
	private function get_task_recipients( array $task_with_fields ): array {
		$recipients = array();

		// Add configured recipients.
		if ( ! empty( $this->recipient ) ) {
			$recipients = array_map( 'trim', explode( ',', $this->recipient ) );
		}

		// Add task creator if enabled.
		if ( wc_string_to_bool( $this->get_option( 'notify_creator', 'yes' ) ) ) {
			$creator_id = $this->get_field_value_from_task( $task_with_fields, 'creator' );
			if ( $creator_id ) {
				$creator = get_userdata( $creator_id );
				if ( $creator ) {
					$recipients[] = $creator->user_email;
				}
			}
		}

		// Add shop managers if enabled.
		if ( wc_string_to_bool( $this->get_option( 'notify_shop_managers', 'no' ) ) ) {
			$shop_managers = get_users(
				array(
					'role__in' => array( 'shop_manager', 'administrator' ),
				)
			);

			foreach ( $shop_managers as $manager ) {
				$recipients[] = $manager->user_email;
			}
		}

		// Remove duplicates and filter valid emails.
		$recipients = array_unique( array_filter( $recipients, 'is_email' ) );

		/**
		 * Filter the email recipients for a task notification.
		 *
		 * @param array $recipients        List of email addresses.
		 * @param array $task_with_fields  Complete task data.
		 * @param self  $email             The email instance.
		 */
		return apply_filters( 'wpo_aom_email_recipients', $recipients, $task_with_fields, $this );
	}

	/**
	 * Get field value from task data by field slug.
	 *
	 * @param array  $task_with_fields Complete task data.
	 * @param string $field_slug       The field slug.
	 *
	 * @return mixed|null
	 */
	private function get_field_value_from_task( array $task_with_fields, string $field_slug ) {
		if ( empty( $task_with_fields['fields'] ) ) {
			return null;
		}

		foreach ( $task_with_fields['fields'] as $field ) {
			if ( $field['slug'] === $field_slug && ! empty( $field['values'] ) ) {
				return $field['values'][0]['raw'] ?? null;
			}
		}

		return null;
	}
}
