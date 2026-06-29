<?php

namespace WPO\AOM\Emails;

use WC_Email;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WPO\AOM\Core\Logger;
use WPO\AOM\Services\TaskManagerService;
use WPO\AOM\Traits\TaskEmailRecipients;
use WPO\AOM\Enums\DefaultTaskFields;

defined( 'ABSPATH' ) || exit;

class TaskUpdatedEmail extends WC_Email {
	use TaskEmailRecipients;
	protected array $task_data = array();
	protected TaskManagerService $task_manager_service;

	/**
	 * Constructor.
	 *
	 * @param TaskManagerService $task_manager_service
	 */
	public function __construct( TaskManagerService $task_manager_service ) {
		$this->task_manager_service = $task_manager_service;

		$this->id             = 'wpo_aom_task_updated';
		$this->title          = __( 'Task Updated', 'wpo-advanced-order-manager' );
		$this->description    = __( 'Task updated emails are sent when a task is modified.', 'wpo-advanced-order-manager' );

		$this->template_html  = 'emails/task-updated.php';
		$this->template_plain = 'emails/plain/task-updated.php';
		$this->template_base  = WPO_AOM()->plugin_path() . '/templates/';
		$this->placeholders = array(
			'{task_title}' => '',
			'{task_id}'    => '',
		);

		// Triggers for this email.
		add_action( 'wpo_aom_task_updated', array( $this, 'trigger' ), 10, 3 );
		add_action( 'wpo_aom_task_moved', array( $this, 'trigger_on_task_moved' ), 10, 4 );

		parent::__construct();

		// Default recipient: shop manager and admin emails.
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * Get email subject.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( '[{site_title}] Task Updated: {task_title}', 'wpo-advanced-order-manager' );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return __( 'Task Updated', 'wpo-advanced-order-manager' );
	}

	/**
	 * Trigger the sending of this email.
	 *
	 * @param int   $task_id          The task ID.
	 * @param array $task_with_fields Complete task data.
	 * @param array $updated_fields   Associative array of field slugs that were changed,
	 *                                with 'old_value' and 'new_value' for each.
	 */
	public function trigger( int $task_id, array $task_with_fields, array $updated_fields = array() ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->setup_locale();

		try {
			$this->task_data = $task_with_fields;

			// Check if we should send the email based on monitored fields.
			$monitored_fields = $this->get_monitored_fields();
			if ( ! empty( $monitored_fields ) ) {
				// Check if any of the updated fields are in the monitored list.
				$should_send = false;
				foreach ( $updated_fields as $field_slug => $changes ) {
					if ( in_array( $field_slug, $monitored_fields, true ) ) {
						$should_send = true;
						break;
					}
				}

				// If no monitored fields were updated, don't send the email.
				if ( ! $should_send ) {
					return;
				}
			}

			// Replace placeholders in subject and heading.
			$this->placeholders['{task_title}'] = $task_with_fields['title'];
			$this->placeholders['{task_id}']    = (string) $task_id;

			$recipients = $this->get_task_recipients( $task_with_fields );

			if ( ! empty( $recipients ) ) {
				$this->recipient = implode( ', ', $recipients );
				$sent            = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

				if ( $sent ) {
					/**
					 * Action hook after task updated email is sent.
					 *
					 * @param int   $task_id          The task ID.
					 * @param array $task_with_fields Complete task data.
					 * @param array $updated_fields   Array of updated field slugs with old and new values.
					 */
					do_action( 'wpo_aom_task_updated_email_sent', $task_id, $task_with_fields, $updated_fields );
				}
			}
		} finally {
			$this->restore_locale();
		}
	}

	/**
	 * Trigger email when a task is moved (with lazy loading).
	 *
	 * @param int   $task_id          The task ID.
	 * @param int   $target_status_id The new status ID.
	 * @param float $new_position     The new position.
	 * @param array $updated_fields   Array of fields that changed with old/new values.
	 *
	 * @return void
	 */
	public function trigger_on_task_moved(
		int $task_id,
		int $target_status_id,
		float $new_position,
		array $updated_fields
	): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Check if status actually changed.
		if (
			! isset( $updated_fields['status'] ) ||
			$updated_fields['status']['old_value'] === $updated_fields['status']['new_value']
		) {
			return;
		}

		// Check if status is in monitored fields.
		$monitored_fields = $this->get_monitored_fields();
		if ( ! empty( $monitored_fields ) && ! in_array( 'status', $monitored_fields, true ) ) {
			return;
		}

		try {
			$task_service = $this->task_manager_service;
			// Fetch full task data.
			$task_with_fields = $task_service->get_task_with_fields( $task_id );

			if ( ! empty( $task_with_fields ) ) {
				$this->trigger( $task_id, $task_with_fields, $updated_fields );
			}
		} catch ( \Throwable $e ) {
			Logger::error( sprintf( 'Failed to send task updated email for task %d: %s', $task_id, $e->getMessage() ) );
		}
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		$this->maybe_init_task_for_preview( $this->task_data );

		return wc_get_template_html(
			$this->template_html,
			array(
				'task_data'          => $this->task_data,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		$this->maybe_init_task_for_preview( $this->task_data );

		return wc_get_template_html(
			$this->template_plain,
			array(
				'task_data'          => $this->task_data,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Initialize a sample task for preview if none exists.
	 *
	 * @param array $task_data Task data.
	 *
	 * @return void
	 */
	public function maybe_init_task_for_preview( array $task_data ): void {
		if ( ! empty( $task_data['id'] ) ) {
			return;
		}

		// Create sample task data with proper structure expected by email templates.
		$this->task_data = array(
			'id'          => 12345,
			'title'       => __( 'Sample Task: Process Customer Order', 'wpo-advanced-order-manager' ),
			'description' => __( 'This is a preview of how task update details will appear in the email notification. The task has been updated with new status and priority.', 'wpo-advanced-order-manager' ),
			'slug'        => 'sample-task-process-customer-order',
			'fields'      => array(
				// Status field with options for formatting.
				array(
					'id'      => DefaultTaskFields::STATUS,
					'slug'    => 'status',
					'label'   => __( 'Status', 'wpo-advanced-order-manager' ),
					'type'    => 'select',
					'options' => array(
						array(
							'id'    => 1,
							'label' => __( 'Pending', 'wpo-advanced-order-manager' ),
							'slug'  => 'pending',
							'color' => '#FFA500',
						),
						array(
							'id'    => 2,
							'label' => __( 'In Progress', 'wpo-advanced-order-manager' ),
							'slug'  => 'in-progress',
							'color' => '#2271b1',
						),
						array(
							'id'    => 3,
							'label' => __( 'Done', 'wpo-advanced-order-manager' ),
							'slug'  => 'done',
							'color' => '#00a32a',
						),
					),
					'values'  => array(
						array(
							'raw'      => 2,
							'resolved' => array(
								'id'    => 2,
								'label' => __( 'In Progress', 'wpo-advanced-order-manager' ),
								'slug'  => 'in-progress',
								'color' => '#2271b1',
							),
						),
					),
				),
				// Priority field with options for formatting.
				array(
					'id'      => DefaultTaskFields::PRIORITY,
					'slug'    => 'priority',
					'label'   => __( 'Priority', 'wpo-advanced-order-manager' ),
					'type'    => 'select',
					'options' => array(
						array(
							'id'    => 1,
							'label' => __( 'Low', 'wpo-advanced-order-manager' ),
							'slug'  => 'low',
							'color' => '#00a32a',
						),
						array(
							'id'    => 2,
							'label' => __( 'Medium', 'wpo-advanced-order-manager' ),
							'slug'  => 'medium',
							'color' => '#FFA500',
						),
						array(
							'id'    => 3,
							'label' => __( 'High', 'wpo-advanced-order-manager' ),
							'slug'  => 'high',
							'color' => '#FF0000',
						),
					),
					'values'  => array(
						array(
							'raw'      => 3,
							'resolved' => array(
								'id'    => 3,
								'label' => __( 'High', 'wpo-advanced-order-manager' ),
								'slug'  => 'high',
								'color' => '#FF0000',
							),
						),
					),
				),
				// Due date field.
				array(
					'id'     => DefaultTaskFields::DUE_DATE,
					'slug'   => 'due_date',
					'label'  => __( 'Due Date', 'wpo-advanced-order-manager' ),
					'type'   => 'date',
					'values' => array(
						array(
							'raw'      => gmdate( 'Y-m-d', strtotime( '+3 days' ) ),
							'resolved' => gmdate( 'Y-m-d', strtotime( '+3 days' ) ),
						),
					),
				),
			),
		);
	}

	/**
	 * Initialize settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$placeholder_text = sprintf(
			'%s: %s',
			__( 'Available placeholders', 'wpo-advanced-order-manager' ),
			'<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>'
		);

		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'wpo-advanced-order-manager' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'wpo-advanced-order-manager' ),
				'default' => 'no',
			),
			'recipient'  => array(
				'title'       => __( 'Recipient(s)', 'wpo-advanced-order-manager' ),
				'type'        => 'text',
				'description' => sprintf(
					/* translators: %s: admin email */
					__( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'wpo-advanced-order-manager' ),
					'<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>'
				),
				'placeholder' => '',
				'default'     => get_option( 'admin_email' ),
				'desc_tip'    => true,
			),
			'subject'    => array(
				'title'       => __( 'Subject', 'wpo-advanced-order-manager' ),
				'type'        => 'text',
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
				'desc_tip'    => true,
			),
			'heading'    => array(
				'title'       => __( 'Email heading', 'wpo-advanced-order-manager' ),
				'type'        => 'text',
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
				'desc_tip'    => true,
			),
			'additional_content' => array(
				'title'       => __( 'Additional content', 'wpo-advanced-order-manager' ),
				'description' => __( 'Text to appear below the main email content.', 'wpo-advanced-order-manager' ),
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'N/A', 'wpo-advanced-order-manager' ),
				'type'        => 'textarea',
				'default'     => '',
				'desc_tip'    => true,
			),
			'notify_on_fields' => array(
				'title'       => __( 'Notify On', 'wpo-advanced-order-manager' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Select which fields changes should trigger this email notification.', 'wpo-advanced-order-manager' ),
				'default'     => array( 'status', 'priority' ),
				'options'     => $this->get_notify_on_field_options(),
				'desc_tip'    => true,
			),
			'notify_creator' => array(
				'title'   => __( 'Notify Task Creator', 'wpo-advanced-order-manager' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send notification to the user who created the task', 'wpo-advanced-order-manager' ),
				'default' => 'yes',
			),
			'notify_shop_managers' => array(
				'title'   => __( 'Notify Shop Managers', 'wpo-advanced-order-manager' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send notification to all shop managers and administrators', 'wpo-advanced-order-manager' ),
				'default' => 'no',
			),
			'email_type' => array(
				'title'       => __( 'Email type', 'wpo-advanced-order-manager' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'wpo-advanced-order-manager' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);

		if ( FeaturesUtil::feature_is_enabled( 'email_improvements' ) ) {
			$this->form_fields['cc']  = $this->get_cc_field();
			$this->form_fields['bcc'] = $this->get_bcc_field();
		}
	}

	/**
	 * Get options for fields that can trigger notifications.
	 *
	 * @return array
	 */
	private function get_notify_on_field_options(): array {
		return apply_filters(
			'wpo_aom_task_updated_email_notify_on_field_options',
			array(
				'title'       => __( 'Title', 'wpo-advanced-order-manager' ),
				'description' => __( 'Description', 'wpo-advanced-order-manager' ),
				'status'      => __( 'Status', 'wpo-advanced-order-manager' ),
				'priority'    => __( 'Priority', 'wpo-advanced-order-manager' ),
				'order'       => __( 'Order', 'wpo-advanced-order-manager' ),
				'due_date'    => __( 'Due Date', 'wpo-advanced-order-manager' ),
			)
		);
	}

	/**
	 * Get fields that should be monitored for notifications.
	 *
	 * @return array
	 */
	private function get_monitored_fields(): array {
		$monitored = $this->get_option( 'notify_on_fields', array() );

		return apply_filters( 'wpo_aom_task_updated_email_monitored_fields', (array) $monitored );
	}

}
