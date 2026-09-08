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
				<?php esc_html_e( 'Task Details', 'advanced-order-manager-for-woocommerce' ); ?>
			</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td class="td" style="padding: 12px;">
				<strong><?php esc_html_e( 'Task ID:', 'advanced-order-manager-for-woocommerce' ); ?></strong> #<?php echo esc_html( $task_data['id'] ); ?>
			</td>
		</tr>
		<tr>
			<td class="td" style="padding: 12px;">
				<strong><?php esc_html_e( 'Title:', 'advanced-order-manager-for-woocommerce' ); ?></strong> <?php echo esc_html( $task_data['title'] ); ?>
			</td>
		</tr>
		<?php if ( ! empty( $task_data['description'] ) ) : ?>
		<tr>
			<td class="td" style="padding: 12px;">
				<strong><?php esc_html_e( 'Description:', 'advanced-order-manager-for-woocommerce' ); ?></strong><br>
				<?php echo wp_kses_post( nl2br( $task_data['description'] ) ); ?>
			</td>
		</tr>
		<?php endif; ?>
		<?php
		/** @var EmailService $email_service Passed in from EmailService::email_task_details(). */

		// Display task fields
		if ( ! empty( $task_data['fields'] ) ) :
			foreach ( $task_data['fields'] as $wpo_aom_field ) :
				if ( empty( $wpo_aom_field['values'] ) || 'position' === $wpo_aom_field['slug'] ) {
					continue;
				}

				$wpo_aom_value = $email_service->format_by_field_type( $wpo_aom_field['slug'], $wpo_aom_field['values'][0], $wpo_aom_field );
				if ( empty( $wpo_aom_value ) ) {
					continue;
				}
				?>
				<tr>
					<td class="td" style="padding: 12px;">
						<strong><?php echo esc_html( $wpo_aom_field['label'] ); ?>:</strong>
						<?php
						echo esc_html( $wpo_aom_value );
						?>
					</td>
				</tr>
			<?php
			endforeach;
		endif;
		?>
	</tbody>
</table>
