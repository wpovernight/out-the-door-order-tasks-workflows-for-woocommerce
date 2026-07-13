<?php

namespace WPO\AOM\Emails;

use WC_Email;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WPO\AOM\Traits\TaskEmailRecipients;

defined( 'ABSPATH' ) || exit;

class TaskCreatedEmail extends WC_Email {
	use TaskEmailRecipients;
	protected array $task_data = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wpo_aom_task_created';
		$this->title          = __( 'Task Created', 'advanced-order-manager' );
		$this->description    = __( 'Task created emails are sent when a new task is created.', 'advanced-order-manager' );

		$this->template_html  = 'emails/task-created.php';
		$this->template_plain = 'emails/plain/task-created.php';
		$this->template_base  = WPO_AOM()->plugin_path() . '/templates/';
		$this->placeholders = array(
			'{task_title}' => '',
			'{task_id}'    => '',
		);

		// Triggers for this email.
		add_action( 'wpo_aom_task_created', array( $this, 'trigger' ), 10, 3 );

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
		return __( '[{site_title}] New Task Created: {task_title}', 'advanced-order-manager' );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return __( 'New Task Created', 'advanced-order-manager' );
	}

	/**
	 * Trigger the sending of this email.
	 *
	 * @param int   $task_id           The task ID.
	 * @param array $task_with_fields  Complete task data.
	 * @param array $field_values      Field values set on creation.
	 */
	public function trigger( int $task_id, array $task_with_fields, array $field_values ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->setup_locale();

		try {
			$this->task_data = $task_with_fields;

			// Replace placeholders in subject and heading.
			$this->placeholders['{task_title}'] = $task_with_fields['title'];
			$this->placeholders['{task_id}']    = (string) $task_id;

			$recipients = $this->get_task_recipients( $task_with_fields );

			if ( ! empty( $recipients ) ) {
				$this->recipient = implode( ', ', $recipients );
				$sent            = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

				if ( $sent ) {
					/**
					 * Action hook after task created email is sent.
					 *
					 * @param int   $task_id          The task ID.
					 * @param array $task_with_fields Complete task data.
					 * @param array $field_values     Field values set on creation.
					 */
					do_action( 'wpo_aom_task_created_email_sent', $task_id, $task_with_fields, $field_values );
				}
			}
		} finally {
			$this->restore_locale();
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
				'sent_to_admin'      => true,
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
				'sent_to_admin'      => true,
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
			'title'       => __( 'Sample Task: Process Customer Order', 'advanced-order-manager' ),
			'description' => __( 'This is a preview of how task details will appear in the email notification. The task has been created and assigned to a team member for processing.', 'advanced-order-manager' ),
			'slug'        => 'sample-task-process-customer-order',
			'fields'      => array(
				// Status field.
				array(
					'id'     => 1,
					'slug'   => 'status',
					'label'  => __( 'Status', 'advanced-order-manager' ),
					'type'   => 'select',
					'values' => array(
						array(
							'raw'      => 1,
							'resolved' => array(
								'id'    => 1,
								'label' => __( 'Pending', 'advanced-order-manager' ),
								'slug'  => 'pending',
								'color' => '#FFA500',
							),
						),
					),
				),
				// Priority field.
				array(
					'id'     => 2,
					'slug'   => 'priority',
					'label'  => __( 'Priority', 'advanced-order-manager' ),
					'type'   => 'select',
					'values' => array(
						array(
							'raw'      => 2,
							'resolved' => array(
								'id'    => 2,
								'label' => __( 'High', 'advanced-order-manager' ),
								'slug'  => 'high',
								'color' => '#FF0000',
							),
						),
					),
				),
				// Creator field.
				array(
					'id'     => 3,
					'slug'   => 'creator',
					'label'  => __( 'Created By', 'advanced-order-manager' ),
					'type'   => 'user',
					'values' => array(
						array(
							'raw'      => 1,
							'resolved' => array(
								'id'       => 1,
								'username' => 'admin',
								'email'    => get_option( 'admin_email' ),
							),
						),
					),
				),
				// Due date field.
				array(
					'id'     => 5,
					'slug'   => 'due_date',
					'label'  => __( 'Due Date', 'advanced-order-manager' ),
					'type'   => 'date',
					'values' => array(
						array(
							'raw'      => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
							'resolved' => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
						),
					),
				),
				// Order field.
				array(
					'id'     => 6,
					'slug'   => 'order',
					'label'  => __( 'Associated Order', 'advanced-order-manager' ),
					'type'   => 'order',
					'values' => array(
						array(
							'raw'      => 123,
							'resolved' => array(
								'id' => 123,
							),
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
			__( 'Available placeholders', 'advanced-order-manager' ),
			'<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>'
		);

		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'advanced-order-manager' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'advanced-order-manager' ),
				'default' => 'no',
			),
			'recipient'  => array(
				'title'       => __( 'Recipient(s)', 'advanced-order-manager' ),
				'type'        => 'text',
				'description' => sprintf(
					/* translators: %s: admin email */
					__( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'advanced-order-manager' ),
					'<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>'
				),
				'placeholder' => '',
				'default'     => get_option( 'admin_email' ),
				'desc_tip'    => true,
			),
			'subject'    => array(
				'title'       => __( 'Subject', 'advanced-order-manager' ),
				'type'        => 'text',
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
				'desc_tip'    => true,
			),
			'heading'    => array(
				'title'       => __( 'Email heading', 'advanced-order-manager' ),
				'type'        => 'text',
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
				'desc_tip'    => true,
			),
			'additional_content' => array(
				'title'       => __( 'Additional content', 'advanced-order-manager' ),
				'description' => __( 'Text to appear below the main email content.', 'advanced-order-manager' ),
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'N/A', 'advanced-order-manager' ),
				'type'        => 'textarea',
				'default'     => '',
				'desc_tip'    => true,
			),
			'notify_creator' => array(
				'title'   => __( 'Notify Task Creator', 'advanced-order-manager' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send notification to the user who created the task', 'advanced-order-manager' ),
				'default' => 'yes',
			),
			'notify_shop_managers' => array(
				'title'   => __( 'Notify Shop Managers', 'advanced-order-manager' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send notification to all shop managers and administrators', 'advanced-order-manager' ),
				'default' => 'no',
			),
			'email_type' => array(
				'title'       => __( 'Email type', 'advanced-order-manager' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'advanced-order-manager' ),
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
}
