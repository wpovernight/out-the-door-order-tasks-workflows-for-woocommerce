<?php

namespace WPO\OTD\Tests\Integration\Services;

use WPO\OTD\Enums\TaskStatusRoleSettingKeys;
use WPO\OTD\Services\TaskManagerSettingsService;
use WPO\OTD\Services\TaskStatusRoleService;
use WPO\OTD\Tests\IntegrationTestCase;

class TaskStatusRoleServiceTest extends IntegrationTestCase {
	private TaskStatusRoleService $service;

	public function set_up() {
		parent::set_up();

		$this->service = $this->make( TaskStatusRoleService::class );
	}

	public function test_reads_the_seeded_roles(): void {
		$this->assertSame( $this->status_option_id( 'done' ), $this->service->get_done_field_option_id() );
		$this->assertSame( $this->status_option_id( 'in_progress' ), $this->service->get_undone_field_option_id() );
	}

	public function test_assigning_roles_persists_them(): void {
		$todo = $this->status_option_id( 'not_started' );

		$this->service->set_done_field_option_id( $todo );
		$this->service->set_undone_field_option_id( $todo );

		$stored = get_option( TaskManagerSettingsService::OPTION_NAME );
		$this->assertSame( $todo, $stored[ TaskStatusRoleSettingKeys::DONE ] );
		$this->assertSame( $todo, $stored[ TaskStatusRoleSettingKeys::UNDONE ] );
		$this->assertSame( 'kanban', $this->make( TaskManagerSettingsService::class )->get( 'view' ), 'Other settings are untouched.' );
	}

	public function test_null_clears_a_role(): void {
		$this->service->set_done_field_option_id( null );
		$this->service->set_undone_field_option_id( null );

		$this->assertNull( $this->service->get_done_field_option_id() );
		$this->assertNull( $this->service->get_undone_field_option_id() );
		$this->assertArrayHasKey( TaskStatusRoleSettingKeys::DONE, get_option( TaskManagerSettingsService::OPTION_NAME ), 'A cleared role is stored as null.' );
	}

	/**
	 * @dataProvider invalid_option_provider
	 */
	public function test_only_status_options_can_hold_a_role( string $setter, string $option, string $message ): void {
		$option_id = 'priority' === $option ? $this->priority_option_id( 'high' ) : 999999;

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( sprintf( $message, $option_id ) );

		$this->service->$setter( $option_id );
	}

	public function invalid_option_provider(): array {
		return array(
			'done, missing option'    => array( 'set_done_field_option_id', 'missing', 'No task field option found with ID %d.' ),
			'done, priority option'   => array( 'set_done_field_option_id', 'priority', 'Option %d does not belong to the status field.' ),
			'undone, missing option'  => array( 'set_undone_field_option_id', 'missing', 'No task field option found with ID %d.' ),
			'undone, priority option' => array( 'set_undone_field_option_id', 'priority', 'Option %d does not belong to the status field.' ),
		);
	}

	public function test_is_field_option_assigned_to_any_role(): void {
		$this->assertTrue( $this->service->is_field_option_assigned_to_any_role( $this->status_option_id( 'done' ) ) );
		$this->assertTrue( $this->service->is_field_option_assigned_to_any_role( $this->status_option_id( 'in_progress' ) ) );
		$this->assertFalse( $this->service->is_field_option_assigned_to_any_role( $this->status_option_id( 'not_started' ) ) );
	}

	public function test_seeding_assigns_the_default_options_when_no_role_is_set(): void {
		$this->service->set_done_field_option_id( null );
		$this->service->set_undone_field_option_id( null );

		$this->service->seed_default_role_assignments();

		$this->assertSame( $this->status_option_id( 'done' ), $this->service->get_done_field_option_id() );
		$this->assertSame( $this->status_option_id( 'in_progress' ), $this->service->get_undone_field_option_id() );
	}

	public function test_seeding_never_overrides_an_assignment(): void {
		$todo = $this->status_option_id( 'not_started' );
		$this->service->set_done_field_option_id( null );
		$this->service->set_undone_field_option_id( $todo );

		$this->service->seed_default_role_assignments();

		$this->assertNull( $this->service->get_done_field_option_id(), 'Seeding skips entirely once either role is set.' );
		$this->assertSame( $todo, $this->service->get_undone_field_option_id() );
	}

	public function test_seeding_skips_default_options_that_are_gone(): void {
		global $wpdb;

		$this->service->set_done_field_option_id( null );
		$this->service->set_undone_field_option_id( null );
		$wpdb->delete( $this->table( 'task_field_options' ), array( 'slug' => 'in_progress' ) );

		$this->service->seed_default_role_assignments();

		$this->assertSame( $this->status_option_id( 'done' ), $this->service->get_done_field_option_id() );
		$this->assertNull( $this->service->get_undone_field_option_id() );
	}
}
