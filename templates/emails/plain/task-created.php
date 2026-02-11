<?php
/**
 * Task Created Email (Plain Text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/task-created.php.
 *
 * @var array    $task_data          Task data array.
 * @var string   $email_heading      Email heading.
 * @var string   $additional_content Additional content.
 * @var bool     $sent_to_admin      Is sent to admin.
 * @var bool     $plain_text         Is plain text.
 * @var WC_Email $email              Email object.
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__( 'A new task has been created:', 'wpo-aom' ) . "\n\n";

/**
 * @hooked WPO_AOM_Emails::email_task_details() Shows the task details.
 */
do_action( 'wpo_aom_email_task_details', $task_data, $sent_to_admin, $plain_text, $email );

echo "\n----------------------------------------\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
