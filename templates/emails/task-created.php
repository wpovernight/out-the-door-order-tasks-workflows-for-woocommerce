<?php
/**
 * Task Created Email (HTML)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/task-created.php.
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
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'A new task has been created:', 'wpo-aom' ); ?></p>

<?php
/**
 * @hooked EmailService::email_task_details() Shows the task details.
 */
do_action( 'wpo_aom_email_task_details', $task_data, $sent_to_admin, $plain_text, $email );
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
do_action( 'woocommerce_email_footer', $email );
