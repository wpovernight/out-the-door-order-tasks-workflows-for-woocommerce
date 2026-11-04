<?php

namespace WPO\OTD\Emails;

defined( 'ABSPATH' ) || exit;

class TaskCreatedEmail extends TaskEmail {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wpo_otd_task_created';
		$this->title          = __( 'Task Created', 'out-the-door-order-tasks-workflows-for-woocommerce' );
		$this->description    = __( 'Task created emails are sent when a new task is created.', 'out-the-door-order-tasks-workflows-for-woocommerce' );

		$this->template_html  = 'emails/task-created.php';
		$this->template_plain = 'emails/plain/task-created.php';

		// Triggers for this email. WooCommerce re-fires each registered email action as "<action>_notification"
		// once its mailer, and so this email, exists (see EmailService::register_email_actions()).
		add_action( 'wpo_otd_task_created_notification', array( $this, 'trigger' ), 10, 3 );

		parent::__construct();
	}

	/**
	 * Get email subject.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( '[{site_title}] New Task Created: {task_title}', 'out-the-door-order-tasks-workflows-for-woocommerce' );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return __( 'New Task Created', 'out-the-door-order-tasks-workflows-for-woocommerce' );
	}

	/**
	 * Trigger the sending of this email.
	 *
	 * @param int   $task_id           The task ID.
	 * @param array $task_with_fields  Complete task data.
	 * @param array $field_values      Field values set on creation.
	 */
	public function trigger( int $task_id, array $task_with_fields, array $field_values ): void {
		$this->send_for_task( $task_id, $task_with_fields, $field_values );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function sent( int $task_id, array $task_with_fields, array $field_values ): void {
		/**
		 * Action hook after task created email is sent.
		 *
		 * @param int   $task_id          The task ID.
		 * @param array $task_with_fields Complete task data.
		 * @param array $field_values     Field values set on creation.
		 */
		do_action( 'wpo_otd_task_created_email_sent', $task_id, $task_with_fields, $field_values );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function preview_task(): array {
		return array(
			'id'          => 12345,
			'title'       => __( 'Sample Task: Process Customer Order', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
			'description' => __( 'This is a preview of how task details will appear in the email notification. The task has been created and assigned to a team member for processing.', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
			'slug'        => 'sample-task-process-customer-order',
			'fields'      => array(
				// Status field.
				array(
					'id'     => 1,
					'slug'   => 'status',
					'label'  => __( 'Status', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'type'   => 'select',
					'values' => array(
						array(
							'raw'      => 1,
							'resolved' => array(
								'id'    => 1,
								'label' => __( 'Pending', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
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
					'label'  => __( 'Priority', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'type'   => 'select',
					'values' => array(
						array(
							'raw'      => 2,
							'resolved' => array(
								'id'    => 2,
								'label' => __( 'High', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
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
					'label'  => __( 'Created By', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
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
					'label'  => __( 'Due Date', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
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
					'label'  => __( 'Associated Order', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
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
}
