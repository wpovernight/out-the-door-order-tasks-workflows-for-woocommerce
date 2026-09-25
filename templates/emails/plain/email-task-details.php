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
echo esc_html( strtoupper( __( 'Task Details', 'advanced-order-manager-for-woocommerce' ) ) ) . "\n";
echo "========================================\n\n";

echo esc_html__( 'Task ID:', 'advanced-order-manager-for-woocommerce' ) . ' #' . esc_html( $task_data['id'] ) . "\n";
echo esc_html__( 'Title:', 'advanced-order-manager-for-woocommerce' ) . ' ' . esc_html( $task_data['title'] ) . "\n";

if ( ! empty( $task_data['description'] ) ) {
	echo esc_html__( 'Description:', 'advanced-order-manager-for-woocommerce' ) . "\n" . esc_html( $task_data['description'] ) . "\n";
}

/** @var EmailService $email_service Passed in from EmailService::email_task_details(). */

// Display task fields
if ( ! empty( $task_data['fields'] ) ) {
	echo "\n";
	foreach ( $task_data['fields'] as $wpo_aom_field ) {
		if ( empty( $wpo_aom_field['values'] ) || 'position' === $wpo_aom_field['slug'] ) {
			continue;
		}
		$wpo_aom_value = $email_service->format_by_field_type( $wpo_aom_field['slug'], $wpo_aom_field['values'][0], $wpo_aom_field );
		if ( ! empty( $wpo_aom_value ) ) {
			echo esc_html( $wpo_aom_field['label'] ) . ': ' . esc_html( $wpo_aom_value ) . "\n";
		}
	}
}

echo "\n";
