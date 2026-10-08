<?php

namespace WPO\OTD\Tests\Integration\Services;

use WPO\OTD\Emails\TaskCreatedEmail;
use WPO\OTD\Emails\TaskUpdatedEmail;
use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Services\EmailService;
use WPO\OTD\Services\TaskManagerService;
use WPO\OTD\Tests\IntegrationTestCase;

/**
 * Registering the emails with WooCommerce and rendering task details. Field formatting is
 * covered by Unit\Services\EmailServiceTest.
 */
class EmailServiceTest extends IntegrationTestCase {
	private EmailService $service;

	public function set_up() {
		parent::set_up();

		$this->service = $this->make( EmailService::class );
	}

	public function test_registers_both_email_classes(): void {
		$emails = $this->service->register_email_classes( array( 'WC_Email_New_Order' => 'existing' ) );

		$this->assertSame( 'existing', $emails['WC_Email_New_Order'] );
		$this->assertInstanceOf( TaskCreatedEmail::class, $emails['WPO_OTD_Task_Created_Email'] );
		$this->assertInstanceOf( TaskUpdatedEmail::class, $emails['WPO_OTD_Task_Updated_Email'] );
	}

	public function test_the_emails_appear_in_woocommerce_settings(): void {
		$ids = array_map( static fn( $email ) => $email->id, WC()->mailer()->get_emails() );

		$this->assertContains( 'wpo_otd_task_created', $ids );
		$this->assertContains( 'wpo_otd_task_updated', $ids );
	}

	public function test_task_details_render_as_html(): void {
		$task  = $this->task( "First line\nSecond line" );
		$email = new TaskCreatedEmail();

		ob_start();
		$this->service->email_task_details( $task, true, false, $email );
		$html = ob_get_clean();

		$this->assertStringContainsString( '#' . $task['id'], $html );
		$this->assertStringContainsString( 'Details &amp; more', $html );
		$this->assertStringContainsString( 'First line<br />', $html );
		$this->assertStringContainsString( '<strong>Status:</strong>', $html );
		$this->assertStringContainsString( 'In progress', $html );
		$this->assertStringContainsString( 'High', $html );
		$this->assertStringNotContainsString( 'Position', $html, 'The internal position field is never shown.' );
	}

	public function test_task_details_render_as_plain_text(): void {
		$task  = $this->task( '' );
		$email = new TaskCreatedEmail();

		ob_start();
		$this->service->email_task_details( $task, false, true, $email );
		$text = ob_get_clean();

		// Escaped here; WC_Email::get_content() decodes entities when it builds a plain email.
		$this->assertStringContainsString( 'Title: Details &amp; more', $text );
		$this->assertStringContainsString( 'Status: In progress', $text );
		$this->assertStringContainsString( 'Priority: High', $text );
		$this->assertStringNotContainsString( '<', $text );
	}

	/**
	 * Create a task in progress with a high priority, as the service returns it.
	 *
	 * @param string $description
	 *
	 * @return array
	 */
	private function task( string $description ): array {
		return $this->make( TaskManagerService::class )->create_task(
			'Details & more',
			$description,
			array(
				array(
					'field_id'   => DefaultTaskFields::STATUS,
					'field_slug' => 'status',
					'value'      => $this->status_option_id( 'in_progress' ),
				),
				array(
					'field_id'   => DefaultTaskFields::PRIORITY,
					'field_slug' => 'priority',
					'value'      => $this->priority_option_id( 'high' ),
				),
			)
		);
	}
}
