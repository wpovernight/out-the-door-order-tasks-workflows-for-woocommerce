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
echo strtoupper( __( 'Task Details', 'wpo-aom' ) ) . "\n";
echo "========================================\n\n";

echo __( 'Task ID:', 'wpo-aom' ) . ' #' . $task_data['id'] . "\n";
echo __( 'Title:', 'wpo-aom' ) . ' ' . $task_data['title'] . "\n";

if ( ! empty( $task_data['description'] ) ) {
	echo __( 'Description:', 'wpo-aom' ) . "\n" . $task_data['description'] . "\n";
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
			echo $field['label'] . ': ' . $value . "\n";
		}
	}
}

echo "\n";
