<?php

namespace WPO\OTD\Tests\Unit\Services;

use Brain\Monkey\Functions;
use WPO\OTD\Services\BaseSettingsService;
use WPO\OTD\Services\TaskManagerSettingsService;
use WPO\OTD\Tests\TestCase;

class BaseSettingsServiceTest extends TestCase {
	/**
	 * The fake wp_options table: option name => value.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	protected function setUp(): void {
		parent::setUp();

		$this->options = array();

		Functions\when( 'get_option' )->alias( fn( $name, $default = false ) => $this->options[ $name ] ?? $default );
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				if ( ( $this->options[ $name ] ?? null ) === $value ) {
					return false;
				}

				$this->options[ $name ] = $value;

				return true;
			}
		);
	}

	public function test_a_subclass_must_name_its_option(): void {
		$this->expectException( \LogicException::class );

		new class() extends BaseSettingsService {
		};
	}

	public function test_get_prefers_stored_values_then_the_call_default_then_registered_defaults(): void {
		$this->options['wpo_otd_task_manager_settings'] = array( 'stored' => 'value' );
		$settings                                       = new TaskManagerSettingsService();

		$this->assertSame( 'value', $settings->get( 'stored', 'fallback' ) );
		$this->assertSame( 'fallback', $settings->get( 'view', 'fallback' ) );
		$this->assertSame( 'kanban', $settings->get( 'view' ) );
		$this->assertNull( $settings->get( 'missing' ) );
	}

	public function test_a_stored_null_is_returned_rather_than_a_default(): void {
		$this->options['wpo_otd_task_manager_settings'] = array( 'view' => null );

		$this->assertNull( ( new TaskManagerSettingsService() )->get( 'view' ) );
	}

	public function test_a_non_array_option_is_treated_as_empty(): void {
		$this->options['wpo_otd_task_manager_settings'] = 'corrupted';

		$this->assertSame( 'kanban', ( new TaskManagerSettingsService() )->get( 'view' ) );
	}

	public function test_the_option_is_read_once(): void {
		$reads = 0;
		Functions\when( 'get_option' )->alias(
			static function () use ( &$reads ) {
				++$reads;

				return array();
			}
		);

		$settings = new TaskManagerSettingsService();
		$settings->get( 'a' );
		$settings->get( 'b' );
		$settings->set( 'c', 1 );

		$this->assertSame( 1, $reads );
	}

	public function test_set_stages_changes_until_saved(): void {
		$settings = new TaskManagerSettingsService();

		$settings->set( 'view', 'list' )->set( 'columns', 3 );

		$this->assertSame( 'list', $settings->get( 'view' ) );
		$this->assertTrue( $settings->has_unsaved_changes() );
		$this->assertArrayNotHasKey( 'wpo_otd_task_manager_settings', $this->options );

		$this->assertTrue( $settings->save() );
		$this->assertFalse( $settings->has_unsaved_changes() );
		$this->assertSame( array( 'view' => 'list', 'columns' => 3 ), $this->options['wpo_otd_task_manager_settings'] );
	}

	public function test_setting_the_same_value_is_not_a_change(): void {
		$this->options['wpo_otd_task_manager_settings'] = array( 'view' => 'list' );
		$settings                                       = new TaskManagerSettingsService();

		$settings->set( 'view', 'list' );

		$this->assertFalse( $settings->has_unsaved_changes() );
	}

	public function test_setting_null_is_a_change(): void {
		$settings = new TaskManagerSettingsService();

		$settings->set( 'role', null );

		$this->assertTrue( $settings->has_unsaved_changes() );
		$this->assertNull( $settings->get( 'role', 'unused default' ), 'A stored null is a value, not an absent key.' );
	}

	public function test_delete_stages_a_removal(): void {
		$this->options['wpo_otd_task_manager_settings'] = array( 'view' => 'list', 'columns' => 3 );
		$settings                                       = new TaskManagerSettingsService();

		$settings->delete( 'missing' );
		$this->assertFalse( $settings->has_unsaved_changes(), 'Deleting an absent key is not a change.' );

		$settings->delete( 'view' );
		$this->assertSame( 'kanban', $settings->get( 'view' ) );
		$this->assertTrue( $settings->save() );
		$this->assertSame( array( 'columns' => 3 ), $this->options['wpo_otd_task_manager_settings'] );
	}

	public function test_save_without_changes_does_not_write(): void {
		Functions\expect( 'update_option' )->never();

		$this->assertTrue( ( new TaskManagerSettingsService() )->save() );
	}

	public function test_save_succeeds_when_the_stored_value_already_matches(): void {
		Functions\when( 'update_option' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( array( 'view' => 'list' ) );

		$settings = new TaskManagerSettingsService();
		$settings->set( 'columns', 3 )->delete( 'columns' );

		$this->assertTrue( $settings->save() );
		$this->assertFalse( $settings->has_unsaved_changes() );
	}

	public function test_a_failed_save_keeps_the_changes_staged(): void {
		Functions\when( 'update_option' )->justReturn( false );

		$settings = new TaskManagerSettingsService();
		$settings->set( 'view', 'list' );

		$this->assertFalse( $settings->save() );
		$this->assertTrue( $settings->has_unsaved_changes() );
		$this->assertSame( 'list', $settings->get( 'view' ) );
	}
}
