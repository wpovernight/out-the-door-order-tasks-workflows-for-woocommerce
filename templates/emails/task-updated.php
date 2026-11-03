<?php
/**
 * Task Updated Email (HTML)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/task-updated.php.
 *
 * @var array    $task_data          Task data array.
 * @var string   $email_heading      Email heading.
 * @var string   $additional_content Additional content.
 * @var bool     $sent_to_admin      Is sent to admin.
 * @var bool     $plain_text         Is plain text.
 * @var WC_Email $email              Email object.
 */

defined( 'ABSPATH' ) || exit;

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook fired by email templates.
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'A task has been updated:', 'out-the-door-order-tasks-workflows-for-woocommerce' ); ?></p>

<?php
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
?>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook fired by email templates.
do_action( 'woocommerce_email_footer', $email );
