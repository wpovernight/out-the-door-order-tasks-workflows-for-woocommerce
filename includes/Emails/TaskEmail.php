<?php

namespace WPO\OTD\Emails;

use WC_Email;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

/**
 * An email about a task, sent to the configured recipients and, when enabled, the task's creator and every shop
 * manager and administrator.
 */
abstract class TaskEmail extends WC_Email {
	protected array $task_data = array();

	/**
	 * Whether the templates are told the email goes to the store's admin.
	 *
	 * @var bool
	 */
	protected bool $sent_to_admin = true;

	/**
	 * Constructor. A subclass sets its id, title, description and templates first.
	 */
	public function __construct() {
		$this->template_base = WPO_OTD()->plugin_path() . '/templates/';
		$this->placeholders  = array(
			'{task_title}' => '',
			'{task_id}'    => '',
		);

		parent::__construct();

		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return $this->render( $this->template_html, false );
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return $this->render( $this->template_plain, true );
	}

	/**
	 * Initialize settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$placeholder_text = sprintf(
			'%s: %s',
			__( 'Available placeholders', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
			'<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>'
		);

		$this->form_fields = array_merge(
			array(
				'enabled'            => array(
					'title'   => __( 'Enable/Disable', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'default' => 'no',
				),
				'recipient'          => array(
					'title'       => __( 'Recipient(s)', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'type'        => 'text',
					'description' => sprintf(
						/* translators: %s: admin email */
						__( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
						'<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>'
					),
					'placeholder' => '',
					'default'     => get_option( 'admin_email' ),
					'desc_tip'    => true,
				),
				'subject'            => array(
					'title'       => __( 'Subject', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'type'        => 'text',
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
					'desc_tip'    => true,
				),
				'heading'            => array(
					'title'       => __( 'Email heading', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'type'        => 'text',
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
					'desc_tip'    => true,
				),
				'additional_content' => array(
					'title'       => __( 'Additional content', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'description' => __( 'Text to appear below the main email content.', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'css'         => 'width:400px; height: 75px;',
					'placeholder' => __( 'N/A', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'type'        => 'textarea',
					'default'     => '',
					'desc_tip'    => true,
				),
			),
			$this->own_form_fields(),
			array(
				'notify_creator'       => array(
					'title'   => __( 'Notify Task Creator', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Send notification to the user who created the task', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'default' => 'yes',
				),
				'notify_shop_managers' => array(
					'title'   => __( 'Notify Shop Managers', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Send notification to all shop managers and administrators', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'default' => 'no',
				),
				'email_type'           => array(
					'title'       => __( 'Email type', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose which format of email to send.', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
			)
		);

		if ( FeaturesUtil::feature_is_enabled( 'email_improvements' ) ) {
			$this->form_fields['cc']  = $this->get_cc_field();
			$this->form_fields['bcc'] = $this->get_bcc_field();
		}
	}

	/**
	 * Send this email about a task, in the email's locale, when it is enabled, should_send() agrees and the task has
	 * recipients. sent() runs after a successful send, still in the email's locale.
	 *
	 * @param int   $task_id          The task ID.
	 * @param array $task_with_fields Complete task data.
	 * @param array $changes          What the trigger passed about the task's fields.
	 *
	 * @return void
	 */
	protected function send_for_task( int $task_id, array $task_with_fields, array $changes ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->setup_locale();

		try {
			$this->task_data                    = $task_with_fields;
			$this->placeholders['{task_title}'] = $task_with_fields['title'];
			$this->placeholders['{task_id}']    = (string) $task_id;

			if ( ! $this->should_send( $changes ) ) {
				return;
			}

			$recipients = $this->get_task_recipients( $task_with_fields );

			if ( ! empty( $recipients ) ) {
				$this->recipient = implode( ', ', $recipients );
				$sent            = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

				if ( $sent ) {
					$this->sent( $task_id, $task_with_fields, $changes );
				}
			}
		} finally {
			$this->restore_locale();
		}
	}

	/**
	 * Whether the changes a trigger passed are worth an email.
	 *
	 * @param array $changes
	 *
	 * @return bool
	 */
	protected function should_send( array $changes ): bool {
		return true;
	}

	/**
	 * Announce that the email went out.
	 *
	 * @param int   $task_id          The task ID.
	 * @param array $task_with_fields Complete task data.
	 * @param array $changes          What the trigger passed about the task's fields.
	 *
	 * @return void
	 */
	abstract protected function sent( int $task_id, array $task_with_fields, array $changes ): void;

	/**
	 * The settings fields this email adds after the additional content, before the recipients to notify.
	 *
	 * @return array
	 */
	protected function own_form_fields(): array {
		return array();
	}

	/**
	 * The sample task the email preview shows.
	 *
	 * @return array
	 */
	abstract protected function preview_task(): array;

	/**
	 * Render a template with the task, showing the sample task when the email has none, as in the preview.
	 *
	 * @param string $template
	 * @param bool   $plain_text
	 *
	 * @return string
	 */
	private function render( string $template, bool $plain_text ): string {
		if ( empty( $this->task_data['id'] ) ) {
			$this->task_data = $this->preview_task();
		}

		return wc_get_template_html(
			$template,
			array(
				'task_data'          => $this->task_data,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => $this->sent_to_admin,
				'plain_text'         => $plain_text,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Get recipients based on task data and settings.
	 *
	 * @param array $task_with_fields Complete task data.
	 *
	 * @return array
	 */
	private function get_task_recipients( array $task_with_fields ): array {
		$recipients = array();

		if ( ! empty( $this->recipient ) ) {
			$recipients = array_map( 'trim', explode( ',', $this->recipient ) );
		}

		if ( wc_string_to_bool( $this->get_option( 'notify_creator', 'yes' ) ) ) {
			$creator_id = $this->get_field_value_from_task( $task_with_fields, 'creator' );
			if ( $creator_id ) {
				$creator = get_userdata( $creator_id );
				if ( $creator ) {
					$recipients[] = $creator->user_email;
				}
			}
		}

		if ( wc_string_to_bool( $this->get_option( 'notify_shop_managers', 'no' ) ) ) {
			$shop_managers = get_users(
				array(
					'role__in' => array( 'shop_manager', 'administrator' ),
				)
			);

			foreach ( $shop_managers as $manager ) {
				$recipients[] = $manager->user_email;
			}
		}

		$recipients = array_unique( array_filter( $recipients, 'is_email' ) );

		/**
		 * Filter the email recipients for a task notification. The addresses returned are not checked again, and a
		 * value that is not an array throws a TypeError.
		 *
		 * @param array     $recipients        List of email addresses. Default the configured recipients and, when
		 *                                     enabled, the task's creator and every shop manager and administrator,
		 *                                     each valid address once.
		 * @param array     $task_with_fields  Complete task data.
		 * @param TaskEmail $email             The email instance.
		 */
		return apply_filters( 'wpo_otd_email_recipients', $recipients, $task_with_fields, $this );
	}

	/**
	 * Get field value from task data by field slug.
	 *
	 * @param array  $task_with_fields Complete task data.
	 * @param string $field_slug       The field slug.
	 *
	 * @return mixed|null
	 */
	private function get_field_value_from_task( array $task_with_fields, string $field_slug ): mixed {
		if ( empty( $task_with_fields['fields'] ) ) {
			return null;
		}

		foreach ( $task_with_fields['fields'] as $field ) {
			if ( $field['slug'] === $field_slug && ! empty( $field['values'] ) ) {
				return $field['values'][0]['raw'] ?? null;
			}
		}

		return null;
	}
}
