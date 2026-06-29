<?php
/**
 * Task Details Email Partial (HTML)
 *
 * This template displays the details of a task in an email.
 *
 * @var array $task_data     Task data array.
 * @var bool $sent_to_admin  Is sent to admin.
 * @var bool $plain_text     Is plain text.
 * @var WC_Email $email      Email object.
 */

use WPO\AOM\Services\EmailService;

defined( 'ABSPATH' ) || exit;
?>

<table cellspacing="0" cellpadding="6"
	   style="border-radius: 5px; overflow: hidden; width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; border: 1px solid #e5e5e5; margin-bottom: 20px;"
	   border="1">
	<thead>
		<tr>
			<th class="td" scope="col" style="background-color: #f8f8f8; padding: 12px;">
				<?php esc_html_e( 'Task Details', 'wpo-advanced-order-manager' ); ?>
			</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td class="td" style="padding: 12px;">
				<strong><?php esc_html_e( 'Task ID:', 'wpo-advanced-order-manager' ); ?></strong> #<?php echo esc_html( $task_data['id'] ); ?>
			</td>
		</tr>
		<tr>
			<td class="td" style="padding: 12px;">
				<strong><?php esc_html_e( 'Title:', 'wpo-advanced-order-manager' ); ?></strong> <?php echo esc_html( $task_data['title'] ); ?>
			</td>
		</tr>
		<?php if ( ! empty( $task_data['description'] ) ) : ?>
		<tr>
			<td class="td" style="padding: 12px;">
				<strong><?php esc_html_e( 'Description:', 'wpo-advanced-order-manager' ); ?></strong><br>
				<?php echo wp_kses_post( nl2br( $task_data['description'] ) ); ?>
			</td>
		</tr>
		<?php endif; ?>
		<?php
		/** @var EmailService $email_service Passed in from EmailService::email_task_details(). */

		// Display task fields
		if ( ! empty( $task_data['fields'] ) ) :
			foreach ( $task_data['fields'] as $field ) :
				if ( empty( $field['values'] ) || 'position' === $field['slug'] ) {
					continue;
				}

				$value = $email_service->format_by_field_type( $field['slug'], $field['values'][0], $field );
				if ( empty( $value ) ) {
					continue;
				}
				?>
				<tr>
					<td class="td" style="padding: 12px;">
						<strong><?php echo esc_html( $field['label'] ); ?>:</strong>
						<?php
						echo esc_html( $value );
						?>
					</td>
				</tr>
			<?php
			endforeach;
		endif;
		?>
	</tbody>
</table>
