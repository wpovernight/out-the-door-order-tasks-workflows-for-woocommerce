<?php
/**
 * Task Details Email Partial (Plain Text)
 *
 * This template displays the details of a task in a plain text email.
 *
 * @var array    $task_data     Task data array.
 * @var bool     $sent_to_admin Is sent to admin.
 * @var bool     $plain_text    Is plain text.
 * @var WC_Email $email         Email object.
 */

use WPO\OTD\Services\EmailService;

defined( 'ABSPATH' ) || exit;

echo "========================================\n";
echo esc_html( strtoupper( __( 'Task Details', 'out-the-door-order-tasks-workflows-for-woocommerce' ) ) ) . "\n";
echo "========================================\n\n";

echo esc_html__( 'Task ID:', 'out-the-door-order-tasks-workflows-for-woocommerce' ) . ' #' . esc_html( $task_data['id'] ) . "\n";
echo esc_html__( 'Title:', 'out-the-door-order-tasks-workflows-for-woocommerce' ) . ' ' . esc_html( $task_data['title'] ) . "\n";

if ( ! empty( $task_data['description'] ) ) {
	echo esc_html__( 'Description:', 'out-the-door-order-tasks-workflows-for-woocommerce' ) . "\n" . esc_html( $task_data['description'] ) . "\n";
}

/** @var EmailService $email_service Passed in from EmailService::email_task_details(). */

// Display task fields
if ( ! empty( $task_data['fields'] ) ) {
	echo "\n";
	foreach ( $task_data['fields'] as $wpo_otd_field ) {
		if ( empty( $wpo_otd_field['values'] ) || 'position' === $wpo_otd_field['slug'] ) {
			continue;
		}
		$wpo_otd_value = $email_service->format_by_field_type( $wpo_otd_field['slug'], $wpo_otd_field['values'][0], $wpo_otd_field );
		if ( ! empty( $wpo_otd_value ) ) {
			echo esc_html( $wpo_otd_field['label'] ) . ': ' . esc_html( $wpo_otd_value ) . "\n";
		}
	}
}

echo "\n";
