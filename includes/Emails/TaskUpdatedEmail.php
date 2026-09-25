<?php

namespace WPO\OTD\Emails;

use WC_Email;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WPO\OTD\Core\Logger;
use WPO\OTD\Services\TaskManagerService;
use WPO\OTD\Traits\TaskEmailRecipients;
use WPO\OTD\Enums\DefaultTaskFields;

defined( 'ABSPATH' ) || exit;

class TaskUpdatedEmail extends WC_Email {
	use TaskEmailRecipients;
	protected array $task_data = array();
	protected readonly TaskManagerService $task_manager_service;

	/**
	 * Constructor.
	 *
	 * @param TaskManagerService $task_manager_service
	 */
	public function __construct( TaskManagerService $task_manager_service ) {
		$this->task_manager_service = $task_manager_service;

		$this->id             = 'wpo_aom_task_updated';
		$this->title          = __( 'Task Updated', 'advanced-order-manager-for-woocommerce' );
		$this->description    = __( 'Task updated emails are sent when a task is modified.', 'advanced-order-manager-for-woocommerce' );

		$this->template_html  = 'emails/task-updated.php';
		$this->template_plain = 'emails/plain/task-updated.php';
		$this->template_base  = WPO_OTD()->plugin_path() . '/templates/';
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
		return __( '[{site_title}] Task Updated: {task_title}', 'advanced-order-manager-for-woocommerce' );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return __( 'Task Updated', 'advanced-order-manager-for-woocommerce' );
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
			'title'       => __( 'Sample Task: Process Customer Order', 'advanced-order-manager-for-woocommerce' ),
			'description' => __( 'This is a preview of how task update details will appear in the email notification. The task has been updated with new status and priority.', 'advanced-order-manager-for-woocommerce' ),
			'slug'        => 'sample-task-process-customer-order',
			'fields'      => array(
				// Status field with options for formatting.
				array(
					'id'      => DefaultTaskFields::STATUS,
					'slug'    => 'status',
					'label'   => __( 'Status', 'advanced-order-manager-for-woocommerce' ),
					'type'    => 'select',
					'options' => array(
						array(
							'id'    => 1,
							'label' => __( 'Pending', 'advanced-order-manager-for-woocommerce' ),
							'slug'  => 'pending',
							'color' => '#FFA500',
						),
						array(
							'id'    => 2,
							'label' => __( 'In Progress', 'advanced-order-manager-for-woocommerce' ),
							'slug'  => 'in-progress',
							'color' => '#2271b1',
						),
						array(
							'id'    => 3,
							'label' => __( 'Done', 'advanced-order-manager-for-woocommerce' ),
							'slug'  => 'done',
							'color' => '#00a32a',
						),
					),
					'values'  => array(
						array(
							'raw'      => 2,
							'resolved' => array(
								'id'    => 2,
								'label' => __( 'In Progress', 'advanced-order-manager-for-woocommerce' ),
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
					'label'   => __( 'Priority', 'advanced-order-manager-for-woocommerce' ),
					'type'    => 'select',
					'options' => array(
						array(
							'id'    => 1,
							'label' => __( 'Low', 'advanced-order-manager-for-woocommerce' ),
							'slug'  => 'low',
							'color' => '#00a32a',
						),
						array(
							'id'    => 2,
							'label' => __( 'Medium', 'advanced-order-manager-for-woocommerce' ),
							'slug'  => 'medium',
							'color' => '#FFA500',
						),
						array(
							'id'    => 3,
							'label' => __( 'High', 'advanced-order-manager-for-woocommerce' ),
							'slug'  => 'high',
							'color' => '#FF0000',
						),
					),
					'values'  => array(
						array(
							'raw'      => 3,
							'resolved' => array(
								'id'    => 3,
								'label' => __( 'High', 'advanced-order-manager-for-woocommerce' ),
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
					'label'  => __( 'Due Date', 'advanced-order-manager-for-woocommerce' ),
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
			__( 'Available placeholders', 'advanced-order-manager-for-woocommerce' ),
			'<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>'
		);

		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'advanced-order-manager-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'advanced-order-manager-for-woocommerce' ),
				'default' => 'no',
			),
			'recipient'  => array(
				'title'       => __( 'Recipient(s)', 'advanced-order-manager-for-woocommerce' ),
				'type'        => 'text',
				'description' => sprintf(
					/* translators: %s: admin email */
					__( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'advanced-order-manager-for-woocommerce' ),
					'<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>'
				),
				'placeholder' => '',
				'default'     => get_option( 'admin_email' ),
				'desc_tip'    => true,
			),
			'subject'    => array(
				'title'       => __( 'Subject', 'advanced-order-manager-for-woocommerce' ),
				'type'        => 'text',
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
				'desc_tip'    => true,
			),
			'heading'    => array(
				'title'       => __( 'Email heading', 'advanced-order-manager-for-woocommerce' ),
				'type'        => 'text',
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
				'desc_tip'    => true,
			),
			'additional_content' => array(
				'title'       => __( 'Additional content', 'advanced-order-manager-for-woocommerce' ),
				'description' => __( 'Text to appear below the main email content.', 'advanced-order-manager-for-woocommerce' ),
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'N/A', 'advanced-order-manager-for-woocommerce' ),
				'type'        => 'textarea',
				'default'     => '',
				'desc_tip'    => true,
			),
			'notify_on_fields' => array(
				'title'       => __( 'Notify On', 'advanced-order-manager-for-woocommerce' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Select which fields changes should trigger this email notification.', 'advanced-order-manager-for-woocommerce' ),
				'default'     => array( 'status', 'priority' ),
				'options'     => $this->get_notify_on_field_options(),
				'desc_tip'    => true,
			),
			'notify_creator' => array(
				'title'   => __( 'Notify Task Creator', 'advanced-order-manager-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send notification to the user who created the task', 'advanced-order-manager-for-woocommerce' ),
				'default' => 'yes',
			),
			'notify_shop_managers' => array(
				'title'   => __( 'Notify Shop Managers', 'advanced-order-manager-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send notification to all shop managers and administrators', 'advanced-order-manager-for-woocommerce' ),
				'default' => 'no',
			),
			'email_type' => array(
				'title'       => __( 'Email type', 'advanced-order-manager-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'advanced-order-manager-for-woocommerce' ),
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
		/**
		 * Filter the fields that can be selected to trigger a task updated notification.
		 *
		 * @param array $options Map of field slug => human-readable label.
		 */
		return apply_filters(
			'wpo_aom_task_updated_email_notify_on_field_options',
			array(
				'title'       => __( 'Title', 'advanced-order-manager-for-woocommerce' ),
				'description' => __( 'Description', 'advanced-order-manager-for-woocommerce' ),
				'status'      => __( 'Status', 'advanced-order-manager-for-woocommerce' ),
				'priority'    => __( 'Priority', 'advanced-order-manager-for-woocommerce' ),
				'order'       => __( 'Order', 'advanced-order-manager-for-woocommerce' ),
				'due_date'    => __( 'Due Date', 'advanced-order-manager-for-woocommerce' ),
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

		/**
		 * Filter the fields monitored for changes when deciding whether to send a notification.
		 *
		 * @param array $monitored List of monitored field slugs.
		 */
		return apply_filters( 'wpo_aom_task_updated_email_monitored_fields', (array) $monitored );
	}

}
