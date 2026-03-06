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

use WPO\AOM\Services\EmailService;

defined( 'ABSPATH' ) || exit;

echo "========================================\n";
echo strtoupper( esc_html__( 'Task Details', 'wpo-aom' ) ) . "\n";
echo "========================================\n\n";

echo esc_html__( 'Task ID:', 'wpo-aom' ) . ' #' . esc_html( $task_data['id'] ) . "\n";
echo esc_html__( 'Title:', 'wpo-aom' ) . ' ' . esc_html( $task_data['title'] ) . "\n";

if ( ! empty( $task_data['description'] ) ) {
	echo esc_html__( 'Description:', 'wpo-aom' ) . "\n" . esc_html( $task_data['description'] ) . "\n";
}

/** @var EmailService $email_service */
$email_service = WPO_AOM()->get_service( EmailService::class );

// Display task fields
if ( ! empty( $task_data['fields'] ) ) {
	echo "\n";
	foreach ( $task_data['fields'] as $field ) {
		if ( empty( $field['values'] ) || 'position' === $field['slug'] ) {
			continue;
		}
		$value = $email_service->format_by_field_type( $field['slug'], $field['values'][0], $field );
		if ( ! empty( $value ) ) {
			echo esc_html( $field['label'] ) . ': ' . esc_html( $value ) . "\n";
		}
	}
}

echo "\n";
