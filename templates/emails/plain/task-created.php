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

echo esc_html__( 'A new task has been created:', 'out-the-door-order-tasks-workflows-for-woocommerce' ) . "\n\n";

/**
 * Fires where the email shows the task's details.
 *
 * @hooked EmailService::email_task_details() Shows the task details.
 *
 * @param array     $task_data     The task with its fields.
 * @param bool      $sent_to_admin Whether the email goes to the store's admin.
 * @param bool      $plain_text    Whether the email is plain text.
 * @param \WC_Email $email         The email.
 */
do_action( 'wpo_otd_email_task_details', $task_data, $sent_to_admin, $plain_text, $email );

echo "\n----------------------------------------\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core filter applied by email templates.
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
