<?php

namespace WPO\OTD\Emails;

use WPO\OTD\Core\Logger;
use WPO\OTD\Services\TaskManagerService;
use WPO\OTD\Enums\DefaultTaskFields;

defined( 'ABSPATH' ) || exit;

class TaskUpdatedEmail extends TaskEmail {
	protected bool $sent_to_admin = false;
	protected readonly TaskManagerService $task_manager_service;

	/**
	 * Constructor.
	 *
	 * @param TaskManagerService $task_manager_service
	 */
	public function __construct( TaskManagerService $task_manager_service ) {
		$this->task_manager_service = $task_manager_service;

		$this->id             = 'wpo_otd_task_updated';
		$this->title          = __( 'Task Updated', 'out-the-door-order-tasks-workflows-for-woocommerce' );
		$this->description    = __( 'Task updated emails are sent when a task is modified.', 'out-the-door-order-tasks-workflows-for-woocommerce' );

		$this->template_html  = 'emails/task-updated.php';
		$this->template_plain = 'emails/plain/task-updated.php';

		// Triggers for this email. WooCommerce re-fires each registered email action as "<action>_notification"
		// once its mailer, and so this email, exists (see EmailService::register_email_actions()).
		add_action( 'wpo_otd_task_updated_notification', array( $this, 'trigger' ), 10, 3 );
		add_action( 'wpo_otd_task_moved_notification', array( $this, 'trigger_on_task_moved' ), 10, 4 );

		parent::__construct();
	}

	/**
	 * Get email subject.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( '[{site_title}] Task Updated: {task_title}', 'out-the-door-order-tasks-workflows-for-woocommerce' );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return __( 'Task Updated', 'out-the-door-order-tasks-workflows-for-woocommerce' );
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
		$this->send_for_task( $task_id, $task_with_fields, $updated_fields );
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
	 * Send only when a monitored field changed, or when no field is monitored.
	 *
	 * @param array $updated_fields Updated field slugs with old and new values.
	 *
	 * @return bool
	 */
	protected function should_send( array $updated_fields ): bool {
		$monitored_fields = $this->get_monitored_fields();

		return empty( $monitored_fields ) || array() !== array_intersect( array_keys( $updated_fields ), $monitored_fields );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function sent( int $task_id, array $task_with_fields, array $updated_fields ): void {
		/**
		 * Action hook after task updated email is sent.
		 *
		 * @param int   $task_id          The task ID.
		 * @param array $task_with_fields Complete task data.
		 * @param array $updated_fields   Array of updated field slugs with old and new values.
		 */
		do_action( 'wpo_otd_task_updated_email_sent', $task_id, $task_with_fields, $updated_fields );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function own_form_fields(): array {
		return array(
			'notify_on_fields' => array(
				'title'       => __( 'Notify On', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Select which fields changes should trigger this email notification.', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
				'default'     => array( 'status', 'priority' ),
				'options'     => $this->get_notify_on_field_options(),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function preview_task(): array {
		return array(
			'id'          => 12345,
			'title'       => __( 'Sample Task: Process Customer Order', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
			'description' => __( 'This is a preview of how task update details will appear in the email notification. The task has been updated with new status and priority.', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
			'slug'        => 'sample-task-process-customer-order',
			'fields'      => array(
				// Status field with options for formatting.
				array(
					'id'      => DefaultTaskFields::STATUS,
					'slug'    => 'status',
					'label'   => __( 'Status', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'type'    => 'select',
					'options' => array(
						array(
							'id'    => 1,
							'label' => __( 'Pending', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
							'slug'  => 'pending',
							'color' => '#FFA500',
						),
						array(
							'id'    => 2,
							'label' => __( 'In Progress', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
							'slug'  => 'in-progress',
							'color' => '#2271b1',
						),
						array(
							'id'    => 3,
							'label' => __( 'Done', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
							'slug'  => 'done',
							'color' => '#00a32a',
						),
					),
					'values'  => array(
						array(
							'raw'      => 2,
							'resolved' => array(
								'id'    => 2,
								'label' => __( 'In Progress', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
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
					'label'   => __( 'Priority', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'type'    => 'select',
					'options' => array(
						array(
							'id'    => 1,
							'label' => __( 'Low', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
							'slug'  => 'low',
							'color' => '#00a32a',
						),
						array(
							'id'    => 2,
							'label' => __( 'Medium', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
							'slug'  => 'medium',
							'color' => '#FFA500',
						),
						array(
							'id'    => 3,
							'label' => __( 'High', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
							'slug'  => 'high',
							'color' => '#FF0000',
						),
					),
					'values'  => array(
						array(
							'raw'      => 3,
							'resolved' => array(
								'id'    => 3,
								'label' => __( 'High', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
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
					'label'  => __( 'Due Date', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
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
			'wpo_otd_task_updated_email_notify_on_field_options',
			array(
				'title'       => __( 'Title', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
				'description' => __( 'Description', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
				'status'      => __( 'Status', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
				'priority'    => __( 'Priority', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
				'order'       => __( 'Order', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
				'due_date'    => __( 'Due Date', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
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
		return apply_filters( 'wpo_otd_task_updated_email_monitored_fields', (array) $monitored );
	}

}
