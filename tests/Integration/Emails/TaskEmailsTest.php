<?php

namespace WPO\OTD\Tests\Integration\Emails;

use ReflectionProperty;
use WPO\OTD\Emails\TaskCreatedEmail;
use WPO\OTD\Emails\TaskUpdatedEmail;
use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Services\TaskManagerService;
use WPO\OTD\Tests\IntegrationTestCase;

/**
 * The task created / task updated emails, sent through WordPress's mock mailer.
 */
class TaskEmailsTest extends IntegrationTestCase {
	private TaskManagerService $tasks;

	public function set_up() {
		parent::set_up();

		reset_phpmailer_instance();

		$this->tasks = $this->make( TaskManagerService::class );
	}

	public function tear_down() {
		reset_phpmailer_instance();

		parent::tear_down();
	}

	public function test_the_first_task_of_a_request_without_a_mailer_is_emailed(): void {
		$this->enable( 'wpo_otd_task_created', array( 'recipient' => 'ops@example.org' ) );

		// Simulate a request where WooCommerce has not built its mailer yet.
		$instance = new ReflectionProperty( \WC_Emails::class, 'instance' );
		$instance->setValue( null, null );

		$this->create_task( 'First of the request' );

		$this->assertCount( 1, $this->sent() );
	}

	public function test_the_first_move_of_a_request_without_a_mailer_is_emailed(): void {
		$this->enable( 'wpo_otd_task_updated', array( 'recipient' => 'ops@example.org' ) );
		$id = $this->create_task( 'Moved first' );

		// Simulate a request where WooCommerce has not built its mailer yet.
		$instance = new ReflectionProperty( \WC_Emails::class, 'instance' );
		$instance->setValue( null, null );
		reset_phpmailer_instance();

		$this->tasks->move_task( $id, $this->status_option_id( 'done' ) );

		$mails = $this->sent();
		$this->assertCount( 1, $mails );
		$this->assertSame( '[Test Blog] Task Updated: Moved first', $mails[0]['subject'] );
	}

	/** ================================
	 *   Task created
	 *  ================================ */

	public function test_task_created_is_disabled_by_default(): void {
		$data  = $this->task_data( 'Quiet' );
		$email = new TaskCreatedEmail();

		$email->trigger( 1, $data, array() );

		$this->assertFalse( $email->is_enabled() );
		$this->assertSame( array(), $this->sent() );
	}

	public function test_task_created_goes_to_the_recipients_and_the_creator(): void {
		$creator = self::factory()->user->create( array( 'user_email' => 'creator@example.org' ) );
		$this->enable( 'wpo_otd_task_created', array( 'recipient' => 'ops@example.org, not-an-email, creator@example.org' ) );
		$data  = $this->task_data( 'Pack order 1001', $creator );
		$email = new TaskCreatedEmail();
		$sent  = 0;
		add_action(
			'wpo_otd_task_created_email_sent',
			static function () use ( &$sent ) {
				++$sent;
			}
		);

		$email->trigger( 7, $data, array() );

		$mails = $this->sent();
		$this->assertCount( 1, $mails );
		$this->assertSame( array( 'ops@example.org', 'creator@example.org' ), $this->addresses( $mails[0] ) );
		$this->assertSame( '[Test Blog] New Task Created: Pack order 1001', $mails[0]['subject'] );
		$this->assertStringContainsString( 'A new task has been created:', $mails[0]['body'] );
		$this->assertStringContainsString( 'Pack order 1001', $mails[0]['body'] );
		$this->assertStringContainsString( 'In progress', $mails[0]['body'], 'The status label is rendered.' );
		$this->assertSame( 1, $sent );
	}

	public function test_the_creator_can_be_left_out_and_managers_added(): void {
		$creator = self::factory()->user->create( array( 'user_email' => 'creator@example.org' ) );
		self::factory()->user->create( array( 'role' => 'shop_manager', 'user_email' => 'manager@example.org' ) );
		$this->enable(
			'wpo_otd_task_created',
			array(
				'recipient'            => '',
				'notify_creator'       => 'no',
				'notify_shop_managers' => 'yes',
			)
		);

		$data = $this->task_data( 'Managers only', $creator );

		( new TaskCreatedEmail() )->trigger( 7, $data, array() );

		$recipients = $this->addresses( $this->sent()[0] );
		$this->assertContains( 'manager@example.org', $recipients );
		$this->assertContains( WP_TESTS_EMAIL, $recipients, 'Administrators count as managers.' );
		$this->assertNotContains( 'creator@example.org', $recipients );
	}

	public function test_recipients_can_be_filtered_and_an_empty_list_sends_nothing(): void {
		$this->enable( 'wpo_otd_task_created', array( 'recipient' => 'ops@example.org' ) );
		add_filter( 'wpo_otd_email_recipients', '__return_empty_array' );

		$data = $this->task_data( 'Nobody' );

		( new TaskCreatedEmail() )->trigger( 7, $data, array() );

		$this->assertSame( array(), $this->sent() );
	}

	public function test_task_created_in_plain_text(): void {
		$this->enable( 'wpo_otd_task_created', array( 'recipient' => 'ops@example.org', 'email_type' => 'plain' ) );

		$data = $this->task_data( 'Pick & pack' );

		( new TaskCreatedEmail() )->trigger( 7, $data, array() );

		$body = $this->sent()[0]['body'];
		$this->assertStringContainsString( 'Title: Pick & pack', $body, 'Entities are decoded in plain emails.' );
		$this->assertStringNotContainsString( '<table', $body );
	}

	public function test_the_preview_renders_a_sample_task(): void {
		$html = ( new TaskCreatedEmail() )->get_content_html();

		$this->assertStringContainsString( 'Sample Task: Process Customer Order', $html );
		$this->assertStringContainsString( 'Pending', $html );
	}

	public function test_task_data_without_fields_only_reaches_the_configured_recipients(): void {
		$this->enable( 'wpo_otd_task_created', array( 'recipient' => 'ops@example.org' ) );

		( new TaskCreatedEmail() )->trigger( 7, array( 'id' => 7, 'title' => 'No fields' ), array() );

		$this->assertSame( array( 'ops@example.org' ), $this->addresses( $this->sent()[0] ) );
	}

	public function test_cc_and_bcc_settings_follow_the_woocommerce_email_improvements_feature(): void {
		update_option( 'woocommerce_feature_email_improvements_enabled', 'no' );
		$this->assertArrayNotHasKey( 'cc', ( new TaskCreatedEmail() )->form_fields );

		update_option( 'woocommerce_feature_email_improvements_enabled', 'yes' );
		foreach ( array( new TaskCreatedEmail(), new TaskUpdatedEmail( $this->tasks ) ) as $email ) {
			$this->assertArrayHasKey( 'cc', $email->form_fields, $email->id );
			$this->assertArrayHasKey( 'bcc', $email->form_fields, $email->id );
		}
	}

	public function test_task_created_settings(): void {
		$email = new TaskCreatedEmail();

		$this->assertSame( 'wpo_otd_task_created', $email->id );
		$this->assertSame( 'no', $email->form_fields['enabled']['default'] );
		$this->assertSame( 'yes', $email->form_fields['notify_creator']['default'] );
		$this->assertSame( 'no', $email->form_fields['notify_shop_managers']['default'] );
		$this->assertSame( WP_TESTS_EMAIL, $email->get_recipient() );
	}

	/** ================================
	 *   Task updated
	 *  ================================ */

	public function test_task_updated_only_sends_for_monitored_fields(): void {
		$this->enable( 'wpo_otd_task_updated', array( 'recipient' => 'ops@example.org' ) );
		$retitled      = $this->task_data( 'Retitled' );
		$reprioritized = $this->task_data( 'Reprioritized' );
		$email         = new TaskUpdatedEmail( $this->tasks );

		$email->trigger( 7, $retitled, array( 'title' => array( 'old_value' => 'A', 'new_value' => 'B' ) ) );
		$this->assertSame( array(), $this->sent(), 'Title is not monitored by default.' );

		$email->trigger( 7, $reprioritized, array( 'priority' => array( 'old_value' => '5', 'new_value' => '6' ) ) );
		$mails = $this->sent();
		$this->assertCount( 1, $mails );
		$this->assertSame( '[Test Blog] Task Updated: Reprioritized', $mails[0]['subject'] );
		$this->assertSame( 1, did_action( 'wpo_otd_task_updated_email_sent' ) );
	}

	public function test_without_monitored_fields_every_update_sends(): void {
		$this->enable( 'wpo_otd_task_updated', array( 'recipient' => 'ops@example.org', 'notify_on_fields' => array() ) );

		$data = $this->task_data( 'Anything' );

		( new TaskUpdatedEmail( $this->tasks ) )->trigger( 7, $data, array( 'title' => array() ) );

		$this->assertCount( 1, $this->sent() );
	}

	public function test_the_monitored_fields_can_be_filtered(): void {
		$this->enable( 'wpo_otd_task_updated', array( 'recipient' => 'ops@example.org' ) );
		add_filter( 'wpo_otd_task_updated_email_monitored_fields', static fn() => array( 'title' ) );

		$data = $this->task_data( 'Retitled' );

		( new TaskUpdatedEmail( $this->tasks ) )->trigger( 7, $data, array( 'title' => array() ) );

		$this->assertCount( 1, $this->sent() );
	}

	public function test_a_move_to_another_status_sends_the_current_task(): void {
		$this->enable( 'wpo_otd_task_updated', array( 'recipient' => 'ops@example.org' ) );
		$id    = $this->create_task( 'Moved task' );
		$email = new TaskUpdatedEmail( $this->tasks );

		$email->trigger_on_task_moved( $id, 3, 1.0, array( 'status' => array( 'old_value' => 2, 'new_value' => 3 ) ) );

		$mails = $this->sent();
		$this->assertCount( 1, $mails );
		$this->assertStringContainsString( 'Moved task', $mails[0]['body'] );
	}

	/**
	 * @dataProvider ignored_move_provider
	 */
	public function test_moves_that_do_not_notify( array $updated_fields, array $settings ): void {
		$this->enable( 'wpo_otd_task_updated', array( 'recipient' => 'ops@example.org' ) + $settings );
		$id = $this->create_task( 'Shuffled' );

		( new TaskUpdatedEmail( $this->tasks ) )->trigger_on_task_moved( $id, 2, 1.5, $updated_fields );

		$this->assertSame( array(), $this->sent() );
	}

	public function ignored_move_provider(): array {
		$changed = array( 'status' => array( 'old_value' => 2, 'new_value' => 3 ) );

		return array(
			'reordered within a column' => array( array( 'status' => array( 'old_value' => 2, 'new_value' => 2 ) ), array() ),
			'no status information'     => array( array(), array() ),
			'status not monitored'      => array( $changed, array( 'notify_on_fields' => array( 'priority' ) ) ),
			'email disabled'            => array( $changed, array( 'enabled' => 'no' ) ),
		);
	}

	public function test_a_failure_while_loading_a_moved_task_is_logged(): void {
		$this->enable( 'wpo_otd_task_updated', array( 'recipient' => 'ops@example.org' ) );
		$id    = $this->create_task( 'Unreadable' );
		$email = new TaskUpdatedEmail( $this->tasks );
		$logs  = array();
		add_filter(
			'wpo_otd_logging_enabled',
			static function ( $enabled, $level, $message ) use ( &$logs ) {
				$logs[] = $message;

				return false;
			},
			10,
			3
		);
		$this->throw_on_queries( '/wpo_otd_tasks\b/', new \RuntimeException( 'Database unavailable' ) );

		$email->trigger_on_task_moved( $id, 3, 1.0, array( 'status' => array( 'old_value' => 2, 'new_value' => 3 ) ) );

		$this->assertSame( array(), $this->sent() );
		$this->assertSame( array( "Failed to send task updated email for task {$id}: Database unavailable" ), $logs );
	}

	public function test_a_move_of_a_missing_task_sends_nothing(): void {
		$this->enable( 'wpo_otd_task_updated', array( 'recipient' => 'ops@example.org' ) );

		( new TaskUpdatedEmail( $this->tasks ) )->trigger_on_task_moved( 999999, 3, 1.0, array( 'status' => array( 'old_value' => 2, 'new_value' => 3 ) ) );

		$this->assertSame( array(), $this->sent() );
	}

	public function test_task_updated_settings_and_preview(): void {
		$email = new TaskUpdatedEmail( $this->tasks );

		$this->assertSame( array( 'status', 'priority' ), $email->form_fields['notify_on_fields']['default'] );
		$this->assertSame(
			array( 'title', 'description', 'status', 'priority', 'order', 'due_date' ),
			array_keys( $email->form_fields['notify_on_fields']['options'] )
		);
		$this->assertStringContainsString( 'Sample Task: Process Customer Order', $email->get_content_plain() );
	}

	/**
	 * Turn an email on with the given settings.
	 *
	 * @param string $email_id
	 * @param array  $settings
	 *
	 * @return void
	 */
	private function enable( string $email_id, array $settings ): void {
		update_option( "woocommerce_{$email_id}_settings", $settings + array( 'enabled' => 'yes' ) );
	}

	/**
	 * Create a task in progress through the service.
	 *
	 * @param string $title
	 *
	 * @return int
	 */
	private function create_task( string $title ): int {
		return $this->tasks->create_task(
			$title,
			'',
			array(
				array(
					'field_id'   => DefaultTaskFields::STATUS,
					'field_slug' => 'status',
					'value'      => $this->status_option_id( 'in_progress' ),
				),
			)
		)['id'];
	}

	/**
	 * Task data as the service builds it, for a task in progress.
	 *
	 * @param string   $title
	 * @param int|null $creator_id
	 *
	 * @return array
	 */
	private function task_data( string $title, ?int $creator_id = null ): array {
		$id = $this->tasks->create_task(
			$title,
			'',
			array_filter(
				array(
					array(
						'field_id'   => DefaultTaskFields::STATUS,
						'field_slug' => 'status',
						'value'      => $this->status_option_id( 'in_progress' ),
					),
					$creator_id ? array(
						'field_id'   => DefaultTaskFields::CREATOR,
						'field_slug' => 'creator',
						'value'      => $creator_id,
					) : null,
				)
			)
		)['id'];

		reset_phpmailer_instance();

		return $this->tasks->get_task_with_fields( $id );
	}

	/**
	 * The emails sent so far.
	 *
	 * @return array<int, array{to: array, subject: string, body: string}>
	 */
	private function sent(): array {
		return tests_retrieve_phpmailer_instance()->mock_sent;
	}

	/**
	 * The "to" addresses of a sent email.
	 *
	 * @param array $mail
	 *
	 * @return string[]
	 */
	private function addresses( array $mail ): array {
		return array_map( static fn( $to ) => $to[0], $mail['to'] );
	}
}
