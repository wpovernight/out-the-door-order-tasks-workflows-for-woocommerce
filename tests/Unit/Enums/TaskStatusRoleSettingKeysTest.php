<?php

namespace WPO\OTD\Tests\Unit\Enums;

use WPO\OTD\Enums\TaskStatusRoleSettingKeys;
use WPO\OTD\Tests\TestCase;

class TaskStatusRoleSettingKeysTest extends TestCase {
	public function test_values_are_stable(): void {
		// Keys inside the stored wpo_otd_task_manager_settings option.
		$this->assertSame( 'role_done_option_id', TaskStatusRoleSettingKeys::DONE );
		$this->assertSame( 'role_undone_option_id', TaskStatusRoleSettingKeys::UNDONE );
	}

	public function test_all_lists_every_key(): void {
		$this->assertSame( array( 'role_done_option_id', 'role_undone_option_id' ), TaskStatusRoleSettingKeys::all() );
	}

	public function test_is_valid(): void {
		$this->assertTrue( TaskStatusRoleSettingKeys::is_valid( 'role_done_option_id' ) );
		$this->assertTrue( TaskStatusRoleSettingKeys::is_valid( 'role_undone_option_id' ) );
		$this->assertFalse( TaskStatusRoleSettingKeys::is_valid( 'done' ) );
		$this->assertFalse( TaskStatusRoleSettingKeys::is_valid( '' ) );
	}
}
