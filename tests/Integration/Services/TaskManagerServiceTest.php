<?php

namespace WPO\OTD\Tests\Integration\Services;

use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Enums\TaskStatusRoleSettingKeys;
use WPO\OTD\Models\Task;
use WPO\OTD\Models\TaskField;
use WPO\OTD\Models\TaskFieldValue;
use WPO\OTD\Services\TaskManagerService;
use WPO\OTD\Services\TaskManagerSettingsService;
use WPO\OTD\Tests\IntegrationTestCase;

/**
 * Tasks, fields, options and field values. Moving, finishing and archiving tasks is covered
 * in TaskMovementTest.
 */
class TaskManagerServiceTest extends IntegrationTestCase {
	private TaskManagerService $service;

	public function set_up() {
		parent::set_up();

		$this->service = $this->make( TaskManagerService::class );
	}

	/** ================================
	 *   create_task()
	 *  ================================ */

	public function test_create_task_stores_the_task_its_values_and_a_position(): void {
		$todo = $this->status_option_id( 'not_started' );

		$task = $this->service->create_task(
			'  Pack <b>order</b>  ',
			"Line one\n<script>x</script>Line two",
			array(
				$this->status( $todo ),
				array( 'field_id' => DefaultTaskFields::PRIORITY, 'field_slug' => 'priority', 'value' => $this->priority_option_id( 'high' ) ),
			)
		);

		$this->assertGreaterThan( 0, $task['id'] );
		$this->assertSame( 'Pack order', $task['title'] );
		$this->assertSame( "Line one\nLine two", $task['description'] );
		$this->assertSame( (string) $todo, $this->field_value( $task['id'], DefaultTaskFields::STATUS ) );
		$this->assertSame( '1', $this->field_value( $task['id'], DefaultTaskFields::POSITION ) );
		$this->assertNull( $this->field_value( $task['id'], DefaultTaskFields::DONE_DATE ) );

		$fields = array_column( $task['fields'], null, 'slug' );
		$this->assertSame( 'High', $fields['priority']['values'][0]['resolved']['label'] );
	}

	public function test_a_task_created_without_a_status_starts_in_the_first_column(): void {
		global $wpdb;

		// Make "in progress" the first column, so the result cannot just be the lowest ID.
		$wpdb->update( $this->table( 'task_field_options' ), array( 'position' => 0 ), array( 'id' => $this->status_option_id( 'in_progress' ) ) );

		$this->service->create_task( 'Already there', '', array( $this->status( $this->status_option_id( 'in_progress' ) ) ) );
		$task = $this->service->create_task( 'No status', '' );

		$this->assertSame( (string) $this->status_option_id( 'in_progress' ), $this->field_value( $task['id'], DefaultTaskFields::STATUS ) );
		$this->assertSame( '2', $this->field_value( $task['id'], DefaultTaskFields::POSITION ) );
	}

	public function test_an_empty_status_counts_as_no_status(): void {
		$task = $this->service->create_task( 'Empty status', '', array( $this->status( 0 ) ) );

		$this->assertSame( (string) $this->status_option_id( 'not_started' ), $this->field_value( $task['id'], DefaultTaskFields::STATUS ) );
	}

	public function test_the_status_is_recognized_by_its_field_id(): void {
		$doing = $this->status_option_id( 'in_progress' );
		$this->create_task( 'First', $doing );

		// No field_slug: the position must still be the end of the "in progress" column.
		$task = $this->service->create_task( 'By ID', '', array( array( 'field_id' => (string) DefaultTaskFields::STATUS, 'value' => (string) $doing ) ) );

		$this->assertSame( (string) $doing, $this->field_value( $task['id'], DefaultTaskFields::STATUS ) );
		$this->assertSame( '2', $this->field_value( $task['id'], DefaultTaskFields::POSITION ) );
	}

	public function test_a_task_cannot_be_created_without_any_status_to_place_it_in(): void {
		global $wpdb;

		$wpdb->delete( $this->table( 'task_field_options' ), array( 'field_id' => DefaultTaskFields::STATUS ) );

		try {
			$this->service->create_task( 'Homeless', '' );
			$this->fail( 'Creating the task must fail.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'There is no status to place the task in.', $e->getMessage() );
		}

		$this->assertSame( 0, $this->count_rows( 'tasks' ), 'Nothing is saved.' );
	}

	/**
	 * @dataProvider empty_title_provider
	 *
	 * @param string $title
	 *
	 * @return void
	 */
	public function test_create_task_requires_a_title_that_is_not_empty_once_sanitized( string $title ): void {
		try {
			$this->service->create_task( $title, 'No title' );
			$this->fail( 'Creating the task must fail.' );
		} catch ( \Exception $e ) {
			$this->assertSame( 'Task title is required and must be a string.', $e->getMessage() );
		}

		$this->assertSame( 0, $this->count_rows( 'tasks' ), 'Nothing is saved.' );
	}

	public function test_a_title_of_zero_is_a_title(): void {
		$task = $this->service->create_task( '0', '' );

		$this->assertSame( '0', $task['title'] );
		$this->assertSame( 1, $this->count_rows( 'tasks' ) );
	}

	public function test_a_title_and_description_longer_than_their_columns_are_cut_between_characters(): void {
		$task = $this->service->create_task( str_repeat( 'é', 300 ), str_repeat( 'é', 40000 ) );

		$this->assertSame( str_repeat( 'é', 255 ), $task['title'], 'The title column holds 255 characters.' );
		$this->assertSame( str_repeat( 'é', 32767 ), $task['description'], 'The description column holds 65535 bytes.' );
		$this->assertSame( str_repeat( 'é', 255 ), $this->service->get_task( (int) $task['id'] )->title );
	}

	public function test_a_title_sanitizing_lengthens_is_cut_once_sanitized(): void {
		$task = $this->service->create_task( str_repeat( '<', 300 ), '' );

		$this->assertSame( str_repeat( '&lt;', 63 ), $task['title'], 'Each "<" is stored as "&lt;", and the cut part of the 64th is dropped.' );
	}

	public function test_a_cut_does_not_leave_a_broken_entity_at_the_end(): void {
		$task = $this->service->create_task( str_repeat( 'a', 253 ) . '<<', '' );

		$this->assertSame( str_repeat( 'a', 253 ), $task['title'], 'The "&l" left of "&lt;" is dropped.' );
	}

	public function test_a_cut_does_not_leave_a_broken_hex_entity_written_with_a_capital_x(): void {
		$task = $this->service->create_task( str_repeat( 'a', 250 ) . '&#X1F;', '' );

		$this->assertSame( str_repeat( 'a', 250 ), $task['title'], 'The "&#X1F" left of "&#X1F;" is dropped.' );
	}

	public function test_a_long_run_of_letters_after_an_ampersand_is_not_taken_for_an_entity(): void {
		$task = $this->service->create_task( 'ab&' . str_repeat( 'c', 300 ) . ';', '' );

		$this->assertSame( 'ab&' . str_repeat( 'c', 252 ), $task['title'], 'No entity name is that long, so the cut text is kept.' );
	}

	public function test_an_entity_the_text_ends_with_is_kept_when_only_characters_were_removed(): void {
		$this->store_only_three_byte_characters_in( 'title' );

		$task = $this->service->create_task( 'R😀&D', '' );

		$this->assertSame( 'R&D', $task['title'] );
	}

	/**
	 * @dataProvider stripped_ampersand_provider
	 *
	 * @param string $title
	 * @param string $stored
	 *
	 * @return void
	 */
	public function test_an_ampersand_left_at_the_end_by_removed_characters_is_kept( string $title, string $stored ): void {
		$this->store_only_three_byte_characters_in( 'title' );

		$this->assertSame( $stored, $this->service->create_task( $title, '' )['title'] );
	}

	/**
	 * Titles whose last "&" is followed only by characters the column cannot store: nothing was cut, so nothing
	 * after it is a broken entity.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function stripped_ampersand_provider(): array {
		return array(
			'a word'           => array( 'Q&A😀', 'Q&A' ),
			'the "&" alone'    => array( '&😀', '&' ),
			'an entity before' => array( 'Pack &lt;3😀', 'Pack &lt;3' ),
		);
	}

	public function test_characters_the_column_cannot_store_are_removed(): void {
		$this->store_only_three_byte_characters_in( 'title' );
		$this->store_only_three_byte_characters_in( 'description' );

		$task = $this->service->create_task( 'Pack 😀 now', 'Fast 😀' );

		$this->assertSame( 'Pack  now', $task['title'] );
		$this->assertSame( 'Fast ', $task['description'] );
	}

	public function test_a_title_of_characters_the_column_cannot_store_alone_is_refused(): void {
		$this->store_only_three_byte_characters_in( 'title' );

		try {
			$this->service->create_task( '😀', '' );
			$this->fail( 'Creating the task must fail.' );
		} catch ( \Exception $e ) {
			$this->assertSame( 'Task title is required and must be a string.', $e->getMessage() );
		}

		$this->assertSame( 0, $this->count_rows( 'tasks' ) );
	}

	/**
	 * Titles with nothing left once sanitized.
	 *
	 * @return array<string, array{string}>
	 */
	public static function empty_title_provider(): array {
		return array(
			'none'         => array( '' ),
			'whitespace'   => array( " \n\t " ),
			'markup alone' => array( '<b></b>' ),
		);
	}

	public function test_new_tasks_go_to_the_end_of_their_status(): void {
		$todo = $this->status_option_id( 'not_started' );

		$this->create_task( 'First', $todo );
		$this->create_task( 'Second', $todo );
		$third = $this->create_task( 'Third', $todo );
		$other = $this->create_task( 'Elsewhere', $this->status_option_id( 'in_progress' ) );

		$this->assertSame( '3', $this->field_value( $third, DefaultTaskFields::POSITION ) );
		$this->assertSame( '1', $this->field_value( $other, DefaultTaskFields::POSITION ) );
	}

	public function test_a_task_created_as_done_gets_a_done_date(): void {
		$id = $this->create_task( 'Already done', $this->status_option_id( 'done' ) );

		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $this->field_value( $id, DefaultTaskFields::DONE_DATE ) );
	}

	public function test_a_given_done_date_is_kept(): void {
		$task = $this->service->create_task(
			'Done long ago',
			'',
			array(
				$this->status( $this->status_option_id( 'done' ) ),
				array( 'field_id' => DefaultTaskFields::DONE_DATE, 'field_slug' => 'done_date', 'value' => '2020-01-01 00:00:00' ),
			)
		);

		$this->assertSame( '2020-01-01 00:00:00', $this->field_value( $task['id'], DefaultTaskFields::DONE_DATE ) );
	}

	public function test_create_task_fires_an_action_with_the_full_task(): void {
		$fired = array();
		add_action(
			'wpo_otd_task_created',
			static function ( ...$args ) use ( &$fired ) {
				$fired[] = $args;
			},
			10,
			3
		);

		$task = $this->service->create_task( 'Hooked', '', array( $this->status( $this->status_option_id( 'not_started' ) ) ) );

		$this->assertCount( 1, $fired );
		$this->assertSame( $task['id'], $fired[0][0] );
		$this->assertSame( $task, $fired[0][1] );
		$this->assertSame( 1.0, $fired[0][2][ DefaultTaskFields::POSITION ] );
	}

	/** ================================
	 *   Reading tasks
	 *  ================================ */

	public function test_get_task_and_get_all_tasks(): void {
		$id = $this->create_task( 'Read me', $this->status_option_id( 'not_started' ) );

		$this->assertInstanceOf( Task::class, $this->service->get_task( $id ) );
		$this->assertNull( $this->service->get_task( $id + 1000 ) );
		$this->assertSame( array( 'Read me' ), array_map( static fn( $task ) => $task->title, $this->service->get_all_tasks() ) );
	}

	public function test_get_task_with_fields_lists_every_field_and_resolves_values(): void {
		$creator = self::factory()->user->create( array( 'display_name' => 'Jane Packer' ) );
		$order   = $this->create_order();
		$todo    = $this->status_option_id( 'not_started' );

		$task = $this->service->create_task(
			'Resolved',
			'',
			array(
				$this->status( $todo ),
				array( 'field_id' => DefaultTaskFields::CREATOR, 'field_slug' => 'creator', 'value' => $creator ),
				array( 'field_id' => DefaultTaskFields::ORDER, 'field_slug' => 'order', 'value' => $order->get_id() ),
				array( 'field_id' => DefaultTaskFields::DUE_DATE, 'field_slug' => 'due_date', 'value' => '2030-05-06 07:08:09' ),
			)
		);

		$result = $this->service->get_task_with_fields( $task['id'] );
		$fields = array_column( $result['fields'], null, 'slug' );

		$this->assertSame( 'Resolved', $result['title'] );
		$this->assertCount( 8, $result['fields'] );
		$this->assertNull( $fields['priority']['values'], 'Fields without a value are listed with null values.' );

		$this->assertSame( $todo, $fields['status']['values'][0]['raw'] );
		$this->assertSame( 'not_started', $fields['status']['values'][0]['resolved']['slug'] );

		$this->assertSame( 1.0, $fields['position']['values'][0]['raw'] );
		$this->assertNull( $fields['position']['values'][0]['resolved'] );

		$this->assertSame( (float) $creator, $fields['creator']['values'][0]['raw'] );
		$this->assertSame( 'Jane Packer', $fields['creator']['values'][0]['resolved']['display_name'] );

		$this->assertSame( $order->get_id(), $fields['order']['values'][0]['resolved']['id'] );
		$this->assertSame( 'Jane Doe', $fields['order']['values'][0]['resolved']['full_name'] );
		$this->assertSame( $order->get_status(), $fields['order']['values'][0]['resolved']['status'] );

		$this->assertSame( '2030-05-06 07:08:09', $fields['due_date']['values'][0]['raw'] );
		$this->assertSame( wp_date( 'c', strtotime( '2030-05-06 07:08:09' ) ), $fields['due_date']['values'][0]['resolved'] );
	}

	public function test_unknown_users_and_orders_are_not_resolved(): void {
		$task = $this->service->create_task(
			'Dangling',
			'',
			array(
				$this->status( $this->status_option_id( 'not_started' ) ),
				array( 'field_id' => DefaultTaskFields::CREATOR, 'field_slug' => 'creator', 'value' => 999999 ),
				array( 'field_id' => DefaultTaskFields::ORDER, 'field_slug' => 'order', 'value' => 999999 ),
			)
		);

		$fields = array_column( $this->service->get_task_with_fields( $task['id'] )['fields'], null, 'slug' );

		$this->assertNull( $fields['creator']['values'][0]['resolved'] );
		$this->assertNull( $fields['order']['values'][0]['resolved'] );
	}

	public function test_get_task_with_fields_of_a_missing_task_is_null(): void {
		$this->assertNull( $this->service->get_task_with_fields( 999999 ) );
	}

	public function test_get_all_tasks_with_fields(): void {
		$this->assertSame( array(), $this->service->get_all_tasks_with_fields() );

		$todo  = $this->status_option_id( 'not_started' );
		$first = $this->create_task( 'First', $todo );
		$this->service->update_field_values( $first, array( DefaultTaskFields::ORDER => '12' ) );
		$this->create_task( 'Second', $todo );

		$tasks = $this->service->get_all_tasks_with_fields();

		$this->assertSame( array( 'First', 'Second' ), array_column( $tasks, 'title' ) );

		$first_fields  = array_column( $tasks[0]['fields'], null, 'slug' );
		$second_fields = array_column( $tasks[1]['fields'], null, 'slug' );
		$this->assertSame( 12.0, $first_fields['order']['values'][0]['raw'] );
		$this->assertNull( $second_fields['order']['values'] );
		$this->assertSame( 2.0, $second_fields['position']['values'][0]['raw'] );
	}

	public function test_a_field_with_several_values_lists_them_all(): void {
		$id = $this->create_task( 'Multi', $this->status_option_id( 'not_started' ) );
		$this->make( \WPO\OTD\Repositories\TaskFieldValueRepository::class )
			->update_task_multiple_field_values( $id, array( DefaultTaskFields::ORDER => array( '11', '12' ) ) );

		$fields = array_column( $this->service->get_task_fields_and_values( $id ), null, 'slug' );

		$this->assertSame( array( 11.0, 12.0 ), array_column( $fields['order']['values'], 'raw' ) );
	}

	public function test_get_field_value_handles_text_null_and_unresolved_selects(): void {
		$text   = new TaskField( array( 'id' => 50, 'label' => 'Notes', 'type' => 'text', 'slug' => 'notes' ) );
		$select = new TaskField( array( 'id' => 51, 'label' => 'Carrier', 'type' => 'select', 'slug' => 'carrier' ) );
		$date   = new TaskField( array( 'id' => 52, 'label' => 'Ship by', 'type' => 'date', 'slug' => 'ship_by' ) );

		$this->assertSame(
			array( 'raw' => 'Fragile', 'resolved' => null ),
			$this->service->get_field_value( new TaskFieldValue( array( 'task_id' => 1, 'field_id' => 50, 'value' => 'Fragile' ) ), $text )
		);
		$this->assertNull( $this->service->get_field_value( new TaskFieldValue( array( 'task_id' => 1, 'field_id' => 50 ) ), $text ) );
		$this->assertSame(
			array( 'raw' => 7, 'resolved' => null ),
			$this->service->get_field_value( new TaskFieldValue( array( 'task_id' => 1, 'field_id' => 51, 'value' => '7' ) ), $select ),
			'Only the status and priority selects are resolved to their options.'
		);
		$this->assertSame(
			array( 'raw' => 'someday', 'resolved' => null ),
			$this->service->get_field_value( new TaskFieldValue( array( 'task_id' => 1, 'field_id' => 52, 'value' => 'someday' ) ), $date )
		);
	}

	public function test_get_field_value_is_filterable(): void {
		$field = new TaskField( array( 'id' => 50, 'label' => 'Notes', 'type' => 'text', 'slug' => 'notes' ) );
		$value = new TaskFieldValue( array( 'task_id' => 1, 'field_id' => 50, 'value' => 'Fragile' ) );

		add_filter(
			'wpo_otd_task_get_field_value',
			static fn( $parsed, $field_value, $task_field ) => array( 'raw' => strtoupper( $field_value->value ), 'resolved' => $task_field->slug ),
			10,
			3
		);

		$this->assertSame( array( 'raw' => 'FRAGILE', 'resolved' => 'notes' ), $this->service->get_field_value( $value, $field ) );
	}

	/** ================================
	 *   update_task()
	 *  ================================ */

	public function test_update_task_requires_an_existing_task(): void {
		$this->expectException( \InvalidArgumentException::class );

		$this->service->update_task( 999999, array( 'title' => 'Ghost' ) );
	}

	public function test_update_task_saves_sanitized_title_and_description(): void {
		$id = $this->create_task( 'Old', $this->status_option_id( 'not_started' ) );

		$task = $this->service->update_task( $id, array( 'title' => ' New <i>title</i> ', 'description' => 'Plain' ) );

		$this->assertSame( 'New title', $task['title'] );
		$this->assertSame( 'Plain', $task['description'] );
		$this->assertSame( 'New title', $this->service->get_task( $id )->title );
	}

	public function test_update_task_cuts_a_title_and_description_longer_than_their_columns(): void {
		$id = $this->create_task( 'Old', $this->status_option_id( 'not_started' ) );

		$task = $this->service->update_task( $id, array( 'title' => str_repeat( 'é', 300 ), 'description' => str_repeat( 'é', 40000 ) ) );

		$this->assertSame( str_repeat( 'é', 255 ), $task['title'] );
		$this->assertSame( str_repeat( 'é', 32767 ), $task['description'] );
		$this->assertSame( str_repeat( 'é', 255 ), $this->service->get_task( $id )->title );
	}

	/**
	 * @dataProvider empty_title_provider
	 *
	 * @param string $title
	 *
	 * @return void
	 */
	public function test_update_task_refuses_a_title_that_is_empty_once_sanitized( string $title ): void {
		$id = $this->create_task( 'Old', $this->status_option_id( 'not_started' ) );

		try {
			$this->service->update_task( $id, array( 'title' => $title, 'description' => 'New' ) );
			$this->fail( 'Updating the task must fail.' );
		} catch ( \Exception $e ) {
			$this->assertSame( 'Task title is required and must be a string.', $e->getMessage() );
		}

		$this->assertSame( 'Old', $this->service->get_task( $id )->title );
		$this->assertSame( '', $this->service->get_task( $id )->description, 'Nothing is saved.' );
	}

	public function test_update_task_takes_a_title_of_zero_and_keeps_the_title_when_none_is_sent(): void {
		$id = $this->create_task( 'Old', $this->status_option_id( 'not_started' ) );

		$this->assertSame( 'Old', $this->service->update_task( $id, array( 'description' => 'New' ) )['title'] );
		$this->assertSame( '0', $this->service->update_task( $id, array( 'title' => '0' ) )['title'] );
	}

	public function test_update_task_only_takes_the_title_and_description(): void {
		$id    = $this->create_task( 'Mine', $this->status_option_id( 'not_started' ) );
		$other = $this->create_task( 'Someone else', $this->status_option_id( 'not_started' ) );

		// A REST body: clearing the guard list first used to make the ID writable.
		$task = $this->service->update_task(
			$id,
			array(
				'guarded'    => array(),
				'id'         => $other,
				'created_at' => 'not a date',
				'title'      => 'Renamed',
			)
		);

		$this->assertSame( $id, $task['id'] );
		$this->assertSame( 'Renamed', $this->service->get_task( $id )->title );
		$this->assertSame( 'Someone else', $this->service->get_task( $other )->title );
	}

	public function test_a_null_description_clears_it(): void {
		$id = $this->service->create_task( 'Described', 'Text', array( $this->status( $this->status_option_id( 'not_started' ) ) ) )['id'];

		$this->assertSame( '', $this->service->update_task( $id, array( 'description' => null ) )['description'] );
	}

	public function test_update_task_reports_only_what_changed(): void {
		$id       = $this->create_task( 'Same', $this->status_option_id( 'not_started' ) );
		$high     = $this->priority_option_id( 'high' );
		$low      = $this->priority_option_id( 'low' );
		$reported = $this->capture_updated_fields();

		$this->service->update_field_values( $id, array( DefaultTaskFields::PRIORITY => $low ) );

		$this->service->update_task(
			$id,
			array( 'title' => 'Same', 'description' => 'Added' ),
			array(
				array( 'field_id' => DefaultTaskFields::PRIORITY, 'field_slug' => 'priority', 'value' => $high ),
				array( 'field_id' => DefaultTaskFields::ORDER, 'field_slug' => 'order', 'value' => '5' ),
				array( 'field_id' => DefaultTaskFields::DUE_DATE, 'value' => '2030-01-01 00:00:00' ),
				array( 'field_slug' => 'archived_date', 'value' => 'no field ID, skipped' ),
				array( 'field_id' => DefaultTaskFields::CREATOR, 'field_slug' => 'creator' ),
			)
		);

		$this->assertSame(
			array(
				'description' => array( 'old_value' => '', 'new_value' => 'Added' ),
				'priority'    => array( 'old_value' => (string) $low, 'new_value' => $high ),
				'order'       => array( 'old_value' => null, 'new_value' => '5' ),
				'due_date'    => array( 'old_value' => null, 'new_value' => '2030-01-01 00:00:00' ),
			),
			$reported()
		);
		$this->assertSame( '2030-01-01 00:00:00', $this->field_value( $id, DefaultTaskFields::DUE_DATE ), 'A value needs no slug.' );
		$this->assertNull( $this->field_value( $id, DefaultTaskFields::ARCHIVED_DATE ), 'An entry without a field ID is skipped.' );
		$this->assertNull( $this->field_value( $id, DefaultTaskFields::CREATOR ), 'An entry without a value is skipped.' );
	}

	/**
	 * @dataProvider slugs_that_do_not_name_the_status_provider
	 */
	public function test_a_status_change_is_recognized_by_its_field_id_whatever_the_slug( array $slug ): void {
		$doing = $this->status_option_id( 'in_progress' );
		$done  = $this->status_option_id( 'done' );
		$this->create_task( 'Already done', $done );
		$id       = $this->create_task( 'Finishing', $doing );
		$reported = $this->capture_updated_fields();

		$this->service->update_task( $id, array(), array( array( 'field_id' => (string) DefaultTaskFields::STATUS, 'value' => $done ) + $slug ) );

		$this->assertSame( array( 'status' => array( 'old_value' => (string) $doing, 'new_value' => $done ) ), $reported() );
		$this->assertSame( (string) $done, $this->field_value( $id, DefaultTaskFields::STATUS ) );
		$this->assertSame( '2', $this->field_value( $id, DefaultTaskFields::POSITION ), 'It goes to the end of the done column.' );
		$this->assertNotNull( $this->field_value( $id, DefaultTaskFields::DONE_DATE ) );
	}

	public function slugs_that_do_not_name_the_status_provider(): array {
		return array(
			'no slug'         => array( array() ),
			'another slug'    => array( array( 'field_slug' => 'priority' ) ),
			'an unknown slug' => array( array( 'field_slug' => 'not_a_field' ) ),
			'an empty slug'   => array( array( 'field_slug' => '' ) ),
		);
	}

	public function test_a_status_slug_on_another_field_does_not_make_it_the_status(): void {
		$todo = $this->status_option_id( 'not_started' );
		$high = $this->priority_option_id( 'high' );
		$this->create_task( 'First', $todo );
		$id       = $this->create_task( 'Second', $todo );
		$reported = $this->capture_updated_fields();

		$this->service->update_task( $id, array(), array( array( 'field_id' => DefaultTaskFields::PRIORITY, 'field_slug' => 'status', 'value' => $high ) ) );

		$this->assertSame( array( 'priority' => array( 'old_value' => null, 'new_value' => $high ) ), $reported() );
		$this->assertSame( (string) $high, $this->field_value( $id, DefaultTaskFields::PRIORITY ) );
		$this->assertSame( (string) $todo, $this->field_value( $id, DefaultTaskFields::STATUS ) );
		$this->assertSame( '2', $this->field_value( $id, DefaultTaskFields::POSITION ), 'The task stays where it was.' );
	}

	public function test_a_value_for_a_field_that_does_not_exist_is_skipped(): void {
		global $wpdb;

		$id       = $this->create_task( 'Unknown field', $this->status_option_id( 'not_started' ) );
		$reported = $this->capture_updated_fields();

		$this->service->update_task(
			$id,
			array(),
			array(
				array( 'field_id' => 999999, 'field_slug' => 'ghost', 'value' => 'lost' ),
				array( 'field_id' => DefaultTaskFields::ORDER, 'field_slug' => 'order', 'value' => '5' ),
			)
		);

		$this->assertSame( array( 'order' => array( 'old_value' => null, 'new_value' => '5' ) ), $reported() );
		$this->assertSame( '5', $this->field_value( $id, DefaultTaskFields::ORDER ) );
		$this->assertSame(
			'0',
			$wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $this->table( 'task_field_values' ) . ' WHERE field_id = %d', 999999 ) )
		);
	}

	public function test_a_field_sent_twice_is_not_reported_when_its_last_value_is_unchanged(): void {
		$todo     = $this->status_option_id( 'not_started' );
		$id       = $this->create_task( 'Twice', $todo );
		$reported = $this->capture_updated_fields();

		$this->service->update_task(
			$id,
			array(),
			array(
				$this->status( $this->status_option_id( 'done' ) ),
				$this->status( $todo ),
			)
		);

		$this->assertSame( array(), $reported() );
		$this->assertSame( (string) $todo, $this->field_value( $id, DefaultTaskFields::STATUS ) );
		$this->assertNull( $this->field_value( $id, DefaultTaskFields::DONE_DATE ) );
	}

	public function test_a_field_sent_twice_is_reported_once_against_the_stored_value(): void {
		$todo     = $this->status_option_id( 'not_started' );
		$done     = $this->status_option_id( 'done' );
		$id       = $this->create_task( 'Twice', $todo );
		$reported = $this->capture_updated_fields();

		$this->service->update_task( $id, array(), array( $this->status( $todo ), $this->status( $done ) ) );

		$this->assertSame( array( 'status' => array( 'old_value' => (string) $todo, 'new_value' => $done ) ), $reported() );
		$this->assertSame( (string) $done, $this->field_value( $id, DefaultTaskFields::STATUS ) );
	}

	public function test_resending_an_unchanged_value_with_another_type_is_not_a_change(): void {
		$id       = $this->create_task( 'Same priority', $this->status_option_id( 'not_started' ) );
		$high     = $this->priority_option_id( 'high' );
		$reported = $this->capture_updated_fields();

		$this->service->update_field_values( $id, array( DefaultTaskFields::PRIORITY => $high ) );
		$this->service->update_task( $id, array(), array( array( 'field_id' => DefaultTaskFields::PRIORITY, 'field_slug' => 'priority', 'value' => $high ) ) );

		$this->assertSame( array(), $reported() );
	}

	public function test_unchanged_multi_values_are_not_reported_whatever_their_order(): void {
		$id       = $this->create_task( 'Multi', $this->status_option_id( 'not_started' ) );
		$reported = $this->capture_updated_fields();

		$this->make( \WPO\OTD\Repositories\TaskFieldValueRepository::class )
			->update_task_multiple_field_values( $id, array( DefaultTaskFields::ORDER => array( '11', '12' ) ) );

		$this->service->update_task( $id, array(), array( array( 'field_id' => DefaultTaskFields::ORDER, 'field_slug' => 'order', 'value' => array( '12', '11' ) ) ) );

		$this->assertSame( array(), $reported() );
	}

	public function test_create_task_fails_when_its_field_values_cannot_be_saved(): void {
		$created = 0;
		add_action(
			'wpo_otd_task_created',
			static function () use ( &$created ) {
				++$created;
			}
		);
		$tasks = $this->count_rows( 'tasks' );
		$this->fail_queries( '/^INSERT INTO \S*task_field_values /' );

		$this->assert_throws(
			\Exception::class,
			"Failed to save the task's field values. Database error: ",
			fn() => $this->service->create_task( 'Unsaved', '', array( $this->status( $this->status_option_id( 'not_started' ) ) ) )
		);
		$this->assertSame( 0, $created, 'A task without its values is not announced.' );
		$this->assertSame( $tasks, $this->count_rows( 'tasks' ), 'A task without a status column is not kept.' );
	}

	public function test_a_created_task_that_cannot_be_removed_after_its_values_failed_is_logged(): void {
		$logs = array();
		add_filter(
			'wpo_otd_logging_enabled',
			static function ( $enabled, $level, $message ) use ( &$logs ) {
				$logs[] = $message;

				return false;
			},
			10,
			3
		);
		$this->fail_queries( '/^INSERT INTO \S*task_field_values /' );
		$this->fail_queries( '/^DELETE FROM `?\w*wpo_otd_tasks`? /' );

		$this->assert_throws(
			\Exception::class,
			"Failed to save the task's field values.",
			fn() => $this->service->create_task( 'Stuck', '', array( $this->status( $this->status_option_id( 'not_started' ) ) ) )
		);
		$this->assertCount( 1, $logs );
		$this->assertMatchesRegularExpression( '/^Task \d+ was created without its field values and could not be removed: \S/', $logs[0] );
	}

	public function test_update_task_fails_when_its_field_values_cannot_be_saved(): void {
		$id      = $this->create_task( 'Values', $this->status_option_id( 'not_started' ) );
		$updated = 0;
		add_action(
			'wpo_otd_task_updated',
			static function () use ( &$updated ) {
				++$updated;
			}
		);
		$this->fail_queries( '/^INSERT INTO \S*task_field_values /' );

		$this->assert_throws(
			\Exception::class,
			"Failed to save the task's field values.",
			fn() => $this->service->update_task( $id, array(), array( array( 'field_id' => DefaultTaskFields::ORDER, 'field_slug' => 'order', 'value' => '12' ) ) )
		);
		$this->assertSame( 0, $updated );
	}

	public function test_a_status_change_saves_its_position_and_done_date_in_the_same_write(): void {
		$id     = $this->create_task( 'Moving', $this->status_option_id( 'not_started' ) );
		$writes = 0;
		add_filter(
			'query',
			static function ( $query ) use ( &$writes ) {
				if ( preg_match( '/^INSERT INTO \S*task_field_values /', (string) $query ) ) {
					++$writes;
				}

				return $query;
			}
		);

		$this->service->update_task( $id, array(), array( $this->status( $this->status_option_id( 'done' ) ) ) );

		$this->assertSame( 1, $writes, 'So they are saved together or not at all.' );
		$this->assertSame( (string) $this->status_option_id( 'done' ), $this->field_value( $id, DefaultTaskFields::STATUS ) );
		$this->assertNotNull( $this->field_value( $id, DefaultTaskFields::DONE_DATE ) );
	}

	public function test_a_status_change_that_cannot_be_saved_changes_nothing(): void {
		$this->create_task( 'Already done', $this->status_option_id( 'done' ) );
		$id = $this->create_task( 'Moving', $this->status_option_id( 'not_started' ) );
		$this->fail_queries( '/^INSERT INTO \S*task_field_values /' );

		$this->assert_throws(
			\Exception::class,
			"Failed to save the task's field values.",
			fn() => $this->service->update_task( $id, array(), array( $this->status( $this->status_option_id( 'done' ) ) ) )
		);

		// The status, its position and its done date are one write.
		$this->assertSame( (string) $this->status_option_id( 'not_started' ), $this->field_value( $id, DefaultTaskFields::STATUS ) );
		$this->assertSame( '1', $this->field_value( $id, DefaultTaskFields::POSITION ) );
		$this->assertNull( $this->field_value( $id, DefaultTaskFields::DONE_DATE ) );
	}

	public function test_changing_the_status_moves_the_task_to_the_end_of_the_new_column(): void {
		$todo  = $this->status_option_id( 'not_started' );
		$doing = $this->status_option_id( 'in_progress' );

		$this->create_task( 'Already doing', $doing );
		$this->create_task( 'Also doing', $doing );
		$id = $this->create_task( 'Moving', $todo );

		$this->service->update_task( $id, array(), array( $this->status( $doing ) ) );

		$this->assertSame( (string) $doing, $this->field_value( $id, DefaultTaskFields::STATUS ) );
		$this->assertSame( '3', $this->field_value( $id, DefaultTaskFields::POSITION ) );
	}

	public function test_keeping_the_status_keeps_the_position(): void {
		$todo = $this->status_option_id( 'not_started' );

		$this->create_task( 'First', $todo );
		$id = $this->create_task( 'Second', $todo );

		$this->service->update_task( $id, array( 'title' => 'Renamed' ), array( $this->status( $todo ) ) );

		$this->assertSame( '2', $this->field_value( $id, DefaultTaskFields::POSITION ) );
	}

	public function test_moving_into_and_out_of_done_sets_and_clears_the_done_date(): void {
		$id = $this->create_task( 'Ship it', $this->status_option_id( 'in_progress' ) );

		$this->service->update_task( $id, array(), array( $this->status( $this->status_option_id( 'done' ) ) ) );
		$this->assertNotNull( $this->field_value( $id, DefaultTaskFields::DONE_DATE ) );

		$this->service->update_task( $id, array(), array( $this->status( $this->status_option_id( 'in_progress' ) ) ) );
		$this->assertNull( $this->field_value( $id, DefaultTaskFields::DONE_DATE ) );
	}

	/** ================================
	 *   delete_task()
	 *  ================================ */

	public function test_delete_task_removes_the_task_and_its_values(): void {
		$id      = $this->create_task( 'Doomed', $this->status_option_id( 'not_started' ) );
		$deleted = array();
		add_action(
			'wpo_otd_task_deleted',
			static function ( $task_id ) use ( &$deleted ) {
				$deleted[] = $task_id;
			}
		);

		$this->assertSame( 1, $this->service->delete_task( $id ) );
		$this->assertNull( $this->service->get_task( $id ) );
		$this->assertSame( 0, $this->count_rows( 'task_field_values' ) );
		$this->assertSame( array( $id ), $deleted );
	}

	public function test_deleting_a_missing_task_fires_nothing(): void {
		$this->assertSame( 0, $this->service->delete_task( 999999 ) );
		$this->assertSame( 0, did_action( 'wpo_otd_task_deleted' ) );
	}

	/** ================================
	 *   Fields
	 *  ================================ */

	public function test_create_update_and_delete_a_field(): void {
		$id = $this->service->create_field( array( 'label' => 'Shipping Carrier', 'type' => 'select' ) );

		$this->assertIsInt( $id );
		$this->assertSame( 1, did_action( 'wpo_otd_field_created' ) );

		$fields = $this->service->get_all_fields();
		$this->assertSame( 'select', $fields['shipping_carrier']->type );

		$this->assertTrue( $this->service->update_field( $id, array( 'label' => 'Carrier', 'type' => 'text', 'is_required' => true ) ) );
		$this->assertSame( 1, did_action( 'wpo_otd_field_updated' ) );

		$updated = $this->service->get_all_fields( 'id' )[ $id ];
		$this->assertSame( 'Carrier', $updated->label );
		$this->assertSame( 'select', $updated->type, 'The type is guarded.' );
		$this->assertTrue( $updated->is_required );

		$this->assertTrue( $this->service->delete_field( $id ) );
		$this->assertSame( 1, did_action( 'wpo_otd_field_deleted' ) );
		$this->assertArrayNotHasKey( 'shipping_carrier', $this->service->get_all_fields() );
	}

	public function test_updating_or_deleting_a_missing_field_fails(): void {
		$this->assertFalse( $this->service->update_field( 999999, array( 'label' => 'Ghost' ) ) );
		$this->assertFalse( $this->service->delete_field( 999999 ) );
		$this->assertSame( 0, did_action( 'wpo_otd_field_deleted' ) );
	}

	public function test_deleting_a_field_cascades_to_its_options_and_values(): void {
		$field_id = $this->service->create_field( array( 'label' => 'Carrier', 'type' => 'select' ) );
		$option   = $this->service->add_field_option( $field_id, array( 'label' => 'DHL' ) );
		$task_id  = $this->create_task( 'Ship', $this->status_option_id( 'not_started' ) );
		$this->service->set_field_value( $task_id, $field_id, (string) $option['id'] );

		// Cache the option, so a stale copy would be served after the delete.
		$this->assertNotNull( $this->service->get_field_option( $option['id'] ) );

		$this->service->delete_field( $field_id );

		$this->assertNull( $this->service->get_field_option( $option['id'] ) );
		$this->assertSame( array(), $this->field_values( $task_id, $field_id ) );
	}

	public function test_all_fields_are_indexed_by_slug_by_default(): void {
		$this->assertSame(
			array( 'status', 'position', 'creator', 'order', 'due_date', 'done_date', 'archived_date', 'priority' ),
			array_keys( $this->service->get_all_fields() )
		);
		$this->assertSame( DefaultTaskFields::all(), array_keys( $this->service->get_all_fields( 'id' ) ) );
	}

	/** ================================
	 *   Field options
	 *  ================================ */

	public function test_reading_options(): void {
		$done = $this->status_option_id( 'done' );

		$this->assertSame( 'Done', $this->service->get_field_option( $done )->label );
		$this->assertNull( $this->service->get_field_option( 999999 ) );
		$this->assertSame( array( 'low', 'medium', 'high', 'critical' ), $this->slugs( $this->service->get_field_options_by_field_id( DefaultTaskFields::PRIORITY ) ) );
		$this->assertSame( array( 'not_started', 'in_progress', 'done' ), $this->slugs( $this->service->get_field_options_by_field_slug( 'status' ) ) );
		$this->assertSame( array(), $this->service->get_field_options_by_field_slug( 'missing' ) );
	}

	public function test_add_field_option_appends_with_a_generated_slug_and_color(): void {
		$option = $this->service->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'On Hold' ) );

		$this->assertSame( 'on-hold', $option['slug'] );
		$this->assertMatchesRegularExpression( '/^#[0-9A-F]{6}$/', $option['color'] );
		$this->assertSame( 4, $option['position'] );
		$this->assertSame( DefaultTaskFields::STATUS, $option['field_id'] );
		$this->assertSame( 'On Hold', $this->service->get_field_option( $option['id'] )->label );
		$this->assertSame( 1, did_action( 'wpo_otd_field_option_created' ) );
	}

	public function test_add_field_option_makes_the_slug_unique_within_the_field(): void {
		$second = $this->service->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Done' ) );
		$third  = $this->service->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Again', 'slug' => 'done' ) );
		$other  = $this->service->add_field_option( DefaultTaskFields::PRIORITY, array( 'label' => 'Done' ) );

		$this->assertSame( 'done-2', $second['slug'] );
		$this->assertSame( 'done-3', $third['slug'] );
		$this->assertSame( 'done', $other['slug'], 'Slugs only need to be unique within their field.' );
	}

	public function test_add_field_option_at_a_position_shifts_the_rest(): void {
		$option = $this->service->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Review', 'color' => '#123456', 'position' => 2 ) );

		$this->assertSame( '#123456', $option['color'] );
		$this->assertSame( array( 'not_started', 'review', 'in_progress', 'done' ), $this->slugs( $this->service->get_field_options_by_field_id( DefaultTaskFields::STATUS ) ) );
	}

	/**
	 * @dataProvider out_of_range_position_provider
	 */
	public function test_add_field_option_appends_for_out_of_range_positions( $position ): void {
		$option = $this->service->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Late', 'position' => $position ) );

		$this->assertSame( 4, $option['position'] );
		$this->assertSame( array( 1, 2, 3, 4 ), array_map( static fn( $o ) => $o->position, $this->service->get_field_options_by_field_id( DefaultTaskFields::STATUS ) ) );
	}

	public function out_of_range_position_provider(): array {
		return array(
			'zero'      => array( 0 ),
			'negative'  => array( -3 ),
			'too large' => array( 50 ),
			'null'      => array( null ),
		);
	}

	public function test_update_field_option(): void {
		$option = $this->service->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Review' ) );

		$updated = $this->service->update_field_option( DefaultTaskFields::STATUS, $option['id'], array( 'label' => 'QA', 'color' => '#abcdef' ) );

		$this->assertSame( 'QA', $updated['label'] );
		$this->assertSame( '#abcdef', $updated['color'] );
		$this->assertSame( 'review', $updated['slug'] );
		$this->assertSame( 'QA', $this->service->get_field_option( $option['id'] )->label );
		$this->assertSame( 1, did_action( 'wpo_otd_field_option_updated' ) );
	}

	public function test_update_field_option_keeps_slugs_unique(): void {
		$review = $this->service->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Review' ) );

		$this->assertSame( 'done-2', $this->service->update_field_option( DefaultTaskFields::STATUS, $review['id'], array( 'slug' => 'done' ) )['slug'] );
		$this->assertSame( 'done-2', $this->service->update_field_option( DefaultTaskFields::STATUS, $review['id'], array( 'slug' => 'done-2', 'label' => 'Same slug' ) )['slug'] );
		$this->assertSame(
			'done-2',
			$this->service->update_field_option( DefaultTaskFields::STATUS, $review['id'], array( 'slug' => 'done' ) )['slug'],
			'The option\'s own current slug does not count as a collision.'
		);
	}

	public function test_update_field_option_rejects_unknown_or_foreign_options(): void {
		try {
			$this->service->update_field_option( DefaultTaskFields::STATUS, 999999, array( 'label' => 'Ghost' ) );
			$this->fail( 'A missing option must be rejected.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'Field option not found.', $e->getMessage() );
		}

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Field option does not belong to the given field.' );

		$this->service->update_field_option( DefaultTaskFields::STATUS, $this->priority_option_id( 'low' ), array( 'label' => 'Moved' ) );
	}

	public function test_delete_field_option(): void {
		$option = $this->service->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Review' ) );

		$this->assertTrue( $this->service->delete_field_option( DefaultTaskFields::STATUS, $option['id'] ) );
		$this->assertSame( array( 'not_started', 'in_progress', 'done' ), $this->slugs( $this->service->get_field_options_by_field_id( DefaultTaskFields::STATUS ) ) );
		$this->assertSame( 1, did_action( 'wpo_otd_field_option_deleted' ) );
	}

	public function test_a_deleted_option_is_not_served_from_the_cache(): void {
		$option = $this->service->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Review' ) );

		$this->service->delete_field_option( DefaultTaskFields::STATUS, $option['id'] );

		$this->assertNull( $this->service->get_field_option( $option['id'] ) );
	}

	public function test_delete_field_option_rejects_unknown_or_foreign_options(): void {
		$this->assert_throws(
			\InvalidArgumentException::class,
			'Field option not found.',
			fn() => $this->service->delete_field_option( DefaultTaskFields::STATUS, 999999 )
		);
		$this->assert_throws(
			\InvalidArgumentException::class,
			'Field option does not belong to the given field.',
			fn() => $this->service->delete_field_option( DefaultTaskFields::STATUS, $this->priority_option_id( 'low' ) )
		);
	}

	public function test_an_option_assigned_to_a_role_cannot_be_deleted(): void {
		$this->assert_throws(
			\RuntimeException::class,
			'This option is assigned to a status role and cannot be deleted.',
			fn() => $this->service->delete_field_option( DefaultTaskFields::STATUS, $this->status_option_id( 'done' ) )
		);
	}

	public function test_an_option_used_by_tasks_cannot_be_deleted(): void {
		$todo = $this->status_option_id( 'not_started' );

		$this->create_task( 'One', $todo );
		$this->create_task( 'Two', $todo );

		$this->assert_throws(
			\RuntimeException::class,
			'2 task(s) are using this option. Move them to a different option before deleting.',
			fn() => $this->service->delete_field_option( DefaultTaskFields::STATUS, $todo )
		);
	}

	public function test_reordering_options(): void {
		$todo  = $this->status_option_id( 'not_started' );
		$doing = $this->status_option_id( 'in_progress' );
		$done  = $this->status_option_id( 'done' );

		$this->service->update_field_option_positions( DefaultTaskFields::STATUS, array( $done, $doing, $todo ) );

		$this->assertSame( array( 'done', 'in_progress', 'not_started' ), $this->slugs( $this->service->get_field_options_by_field_id( DefaultTaskFields::STATUS ) ) );
		$this->assertSame( 1, $this->service->get_field_option( $done )->position, 'The option cache is cleared.' );
	}

	public function test_the_new_option_order_can_be_filtered(): void {
		$todo  = $this->status_option_id( 'not_started' );
		$doing = $this->status_option_id( 'in_progress' );
		$done  = $this->status_option_id( 'done' );

		add_filter( 'wpo_otd_field_ordered_option_ids', static fn( $ids ) => array_reverse( $ids ) );

		$this->service->update_field_option_positions( DefaultTaskFields::STATUS, array( $done, $doing, $todo ) );

		$this->assertSame( array( 'not_started', 'in_progress', 'done' ), $this->slugs( $this->service->get_field_options_by_field_id( DefaultTaskFields::STATUS ) ) );
	}

	/**
	 * @dataProvider invalid_reorder_provider
	 */
	public function test_reordering_rejects_invalid_lists( callable $arguments, string $message ): void {
		[ $field_id, $option_ids ] = $arguments( $this );

		$this->assert_throws(
			\InvalidArgumentException::class,
			$message,
			fn() => $this->service->update_field_option_positions( $field_id, $option_ids )
		);
	}

	public function invalid_reorder_provider(): array {
		return array(
			'missing field'      => array(
				static fn() => array( 999999, array( 1 ) ),
				'Field with ID 999999 does not exist.',
			),
			'field without options' => array(
				static fn() => array( DefaultTaskFields::POSITION, array( 1 ) ),
				'Field 2 has no options to reorder.',
			),
			'foreign option'     => array(
				static fn( $test ) => array( DefaultTaskFields::STATUS, array( $test->priority_option_id( 'low' ) ) ),
				'does not belong to field 1.',
			),
			'duplicate option'   => array(
				static fn( $test ) => array( DefaultTaskFields::STATUS, array( $test->status_option_id( 'done' ), $test->status_option_id( 'done' ), $test->status_option_id( 'not_started' ) ) ),
				'Duplicate option IDs detected',
			),
			'incomplete list'    => array(
				static fn( $test ) => array( DefaultTaskFields::STATUS, array( $test->status_option_id( 'done' ) ) ),
				'Expected 3 option IDs, got 1.',
			),
		);
	}

	/** ================================
	 *   Field values
	 *  ================================ */

	public function test_set_field_value_inserts_then_updates(): void {
		$id = $this->create_task( 'Values', $this->status_option_id( 'not_started' ) );

		$this->assertTrue( $this->service->set_field_value( $id, DefaultTaskFields::ORDER, 10 ) );
		$this->assertTrue( $this->service->set_field_value( $id, DefaultTaskFields::ORDER, 11 ) );

		$this->assertSame( array( '11' ), $this->field_values( $id, DefaultTaskFields::ORDER ) );
	}

	public function test_set_field_value_serializes_arrays(): void {
		$id = $this->create_task( 'Values', $this->status_option_id( 'not_started' ) );

		$this->service->set_field_value( $id, DefaultTaskFields::ORDER, array( 1, 2 ) );

		$this->assertSame( array( 1, 2 ), maybe_unserialize( $this->field_value( $id, DefaultTaskFields::ORDER ) ) );
	}

	public function test_set_field_value_reports_a_failed_insert(): void {
		global $wpdb;

		$wpdb->suppress_errors( true );
		$saved = $this->service->set_field_value( 999999, DefaultTaskFields::ORDER, 10 );
		$wpdb->suppress_errors( false );

		$this->assertFalse( $saved, 'The task does not exist, so the foreign key rejects the row.' );
	}

	public function test_update_field_values_sets_each_field(): void {
		$id = $this->create_task( 'Values', $this->status_option_id( 'not_started' ) );

		$this->assertTrue(
			$this->service->update_field_values(
				$id,
				array(
					DefaultTaskFields::ORDER    => 10,
					DefaultTaskFields::DUE_DATE => '2030-01-01',
				)
			)
		);

		$values = $this->service->get_field_values_for_task( $id );
		$this->assertSame( '10', $values[ DefaultTaskFields::ORDER ][0]->value );
		$this->assertSame( '2030-01-01', $values[ DefaultTaskFields::DUE_DATE ][0]->value );
		$this->assertArrayNotHasKey( DefaultTaskFields::PRIORITY, $values );
	}

	public function test_update_field_values_reports_any_failure(): void {
		global $wpdb;

		$wpdb->suppress_errors( true );
		$saved = $this->service->update_field_values( 999999, array( DefaultTaskFields::ORDER => 10 ) );
		$wpdb->suppress_errors( false );

		$this->assertFalse( $saved );
	}

	public function test_a_value_for_a_field_that_does_not_exist_is_not_saved_or_announced(): void {
		global $wpdb;

		$id = $this->create_task( 'Unknown field', $this->status_option_id( 'not_started' ) );
		$this->service->set_field_value( $id, DefaultTaskFields::ORDER, 10 );
		$updates = $this->record_updates();

		// Without the foreign key, which only InnoDB enforces, an attempted insert would save the value.
		$this->throw_on_queries( '/^INSERT INTO `?\w*task_field_values`? /', new \RuntimeException( 'Insert attempted' ) );

		$this->assertFalse( $this->service->set_field_value( $id, 999999, 'lost' ) );
		$this->assertFalse( $this->service->update_field_values( $id, array( DefaultTaskFields::ORDER => 11, 999999 => 'lost' ) ) );

		$this->assertSame( '11', $this->field_value( $id, DefaultTaskFields::ORDER ), 'The value that saved stays saved.' );
		$this->assertSame(
			'0',
			$wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $this->table( 'task_field_values' ) . ' WHERE field_id = %d', 999999 ) )
		);
		$this->assertSame( array(), $updates() );
	}

	/** ================================
	 *   wpo_otd_task_updated from field values
	 *  ================================ */

	public function test_set_field_value_announces_the_change_once(): void {
		$id      = $this->create_task( 'Values', $this->status_option_id( 'not_started' ) );
		$updates = $this->record_updates();

		$this->service->set_field_value( $id, DefaultTaskFields::ORDER, 10 );

		$this->assertCount( 1, $updates() );
		list( $task_id, $task, $updated_fields ) = $updates()[0];
		$this->assertSame( $id, $task_id );
		$this->assertSame( $id, $task['id'] );
		$this->assertSame( 10.0, array_column( $task['fields'], null, 'slug' )['order']['values'][0]['raw'], 'The task is read after the change.' );
		$this->assertSame( array( 'order' => array( 'old_value' => null, 'new_value' => 10 ) ), $updated_fields );
	}

	public function test_set_field_value_reports_the_old_and_new_status(): void {
		$todo    = $this->status_option_id( 'not_started' );
		$doing   = $this->status_option_id( 'in_progress' );
		$id      = $this->create_task( 'Values', $todo );
		$updates = $this->record_updates();

		$this->service->set_field_value( $id, DefaultTaskFields::STATUS, $doing );

		$this->assertCount( 1, $updates() );
		$this->assertSame(
			array( 'status' => array( 'old_value' => (string) $todo, 'new_value' => $doing ) ),
			$updates()[0][2],
			'Keyed and shaped as update_task() reports a status change.'
		);
	}

	public function test_set_field_value_announces_nothing_when_the_value_is_unchanged(): void {
		$id = $this->create_task( 'Values', $this->status_option_id( 'not_started' ) );
		$this->service->set_field_value( $id, DefaultTaskFields::ORDER, 10 );
		$updates = $this->record_updates();

		$this->assertTrue( $this->service->set_field_value( $id, DefaultTaskFields::ORDER, 10 ) );
		$this->assertTrue( $this->service->set_field_value( $id, DefaultTaskFields::ORDER, '10' ) );

		$this->assertSame( array(), $updates() );
	}

	public function test_a_failed_set_field_value_announces_nothing(): void {
		global $wpdb;

		$id = $this->create_task( 'Values', $this->status_option_id( 'not_started' ) );
		$this->service->set_field_value( $id, DefaultTaskFields::ORDER, 10 );
		$updates = $this->record_updates();

		$wpdb->suppress_errors( true );
		$inserted = $this->service->set_field_value( 999999, DefaultTaskFields::ORDER, 10 );
		$wpdb->suppress_errors( false );
		$this->fail_queries( '/^UPDATE `?\w*wpo_otd_task_field_values`? /' );
		$updated = $this->service->set_field_value( $id, DefaultTaskFields::ORDER, 11 );

		$this->assertFalse( $inserted );
		$this->assertFalse( $updated );
		$this->assertSame( array(), $updates() );
	}

	public function test_a_throwing_set_field_value_announces_nothing(): void {
		$id      = $this->create_task( 'Values', $this->status_option_id( 'not_started' ) );
		$updates = $this->record_updates();
		$this->throw_on_queries( '/^INSERT INTO `?\w*task_field_values`? /', new \RuntimeException( 'Deadlock' ) );

		$this->assert_throws(
			\RuntimeException::class,
			'Deadlock',
			fn() => $this->service->set_field_value( $id, DefaultTaskFields::ORDER, 10 )
		);
		$this->assertSame( array(), $updates() );
	}

	public function test_update_field_values_announces_every_change_once(): void {
		$todo  = $this->status_option_id( 'not_started' );
		$doing = $this->status_option_id( 'in_progress' );
		$id    = $this->create_task( 'Values', $todo );
		$this->service->set_field_value( $id, DefaultTaskFields::ORDER, 10 );
		$updates = $this->record_updates();

		$this->assertTrue(
			$this->service->update_field_values(
				$id,
				array(
					DefaultTaskFields::ORDER    => 10,
					DefaultTaskFields::DUE_DATE => '2030-01-01',
					DefaultTaskFields::STATUS   => $doing,
				)
			)
		);

		$this->assertCount( 1, $updates() );
		list( $task_id, $task, $updated_fields ) = $updates()[0];
		$this->assertSame( $id, $task_id );
		$this->assertSame( $doing, array_column( $task['fields'], null, 'slug' )['status']['values'][0]['raw'] );
		$this->assertSame(
			array(
				'due_date' => array( 'old_value' => null, 'new_value' => '2030-01-01' ),
				'status'   => array( 'old_value' => (string) $todo, 'new_value' => $doing ),
			),
			$updated_fields,
			'The unchanged order is left out.'
		);
	}

	public function test_update_field_values_announces_nothing_when_nothing_changed(): void {
		$id = $this->create_task( 'Values', $this->status_option_id( 'not_started' ) );
		$this->service->set_field_value( $id, DefaultTaskFields::ORDER, 10 );
		$updates = $this->record_updates();

		$this->assertTrue( $this->service->update_field_values( $id, array( DefaultTaskFields::ORDER => '10' ) ) );
		$this->assertTrue( $this->service->update_field_values( $id, array() ) );

		$this->assertSame( array(), $updates() );
	}

	public function test_update_field_values_announces_nothing_when_a_value_fails(): void {
		$id = $this->create_task( 'Values', $this->status_option_id( 'not_started' ) );
		$this->service->set_field_value( $id, DefaultTaskFields::ORDER, 10 );
		$updates = $this->record_updates();
		$this->fail_queries( '/^INSERT INTO `?\w*task_field_values`? /' );

		$saved = $this->service->update_field_values(
			$id,
			array(
				DefaultTaskFields::ORDER    => 11,
				DefaultTaskFields::DUE_DATE => '2030-01-01',
			)
		);

		$this->assertFalse( $saved );
		$this->assertSame( '11', $this->field_value( $id, DefaultTaskFields::ORDER ), 'The value that saved stays saved.' );
		$this->assertSame( array(), $updates() );
	}

	public function test_a_throwing_update_field_values_announces_nothing(): void {
		$id = $this->create_task( 'Values', $this->status_option_id( 'not_started' ) );
		$this->service->set_field_value( $id, DefaultTaskFields::ORDER, 10 );
		$updates = $this->record_updates();
		$this->throw_on_queries( '/^INSERT INTO `?\w*task_field_values`? /', new \RuntimeException( 'Deadlock' ) );

		$this->assert_throws(
			\RuntimeException::class,
			'Deadlock',
			fn() => $this->service->update_field_values(
				$id,
				array(
					DefaultTaskFields::ORDER    => 11,
					DefaultTaskFields::DUE_DATE => '2030-01-01',
				)
			)
		);
		$this->assertSame( array(), $updates() );
	}

	public function test_update_task_still_announces_once(): void {
		$todo    = $this->status_option_id( 'not_started' );
		$doing   = $this->status_option_id( 'in_progress' );
		$id      = $this->create_task( 'Values', $todo );
		$updates = $this->record_updates();

		$this->service->update_task( $id, array( 'title' => 'Renamed' ), array( $this->status( $doing ) ) );

		$this->assertCount( 1, $updates() );
		$this->assertSame(
			array(
				'title'  => array( 'old_value' => 'Values', 'new_value' => 'Renamed' ),
				'status' => array( 'old_value' => (string) $todo, 'new_value' => $doing ),
			),
			$updates()[0][2]
		);
	}

	public function test_creating_moving_and_finishing_a_task_announce_no_update(): void {
		$updates = $this->record_updates();

		$id = $this->create_task( 'Values', $this->status_option_id( 'not_started' ) );
		$this->service->move_task( $id, $this->status_option_id( 'in_progress' ) );
		$this->service->move_task( $id, $this->status_option_id( 'not_started' ), null, 'last', array( DefaultTaskFields::ORDER => 10 ) );
		$this->service->mark_task_finished( $id );

		$this->assertSame( array(), $updates() );
	}

	/**
	 * Create a task in a status and return its ID.
	 *
	 * @param string $title
	 * @param int    $status_id
	 *
	 * @return int
	 */
	private function create_task( string $title, int $status_id ): int {
		return $this->service->create_task( $title, '', array( $this->status( $status_id ) ) )['id'];
	}

	/**
	 * A field value entry setting the status.
	 *
	 * @param int $status_id
	 *
	 * @return array
	 */
	private function status( int $status_id ): array {
		return array(
			'field_id'   => DefaultTaskFields::STATUS,
			'field_slug' => 'status',
			'value'      => $status_id,
		);
	}

	/**
	 * Record the updated fields reported by the next wpo_otd_task_updated action.
	 *
	 * @return callable Returns the captured updated fields.
	 */
	private function capture_updated_fields(): callable {
		$captured = null;

		add_action(
			'wpo_otd_task_updated',
			static function ( $task_id, $task, $updated_fields ) use ( &$captured ) {
				$captured = $updated_fields;
			},
			10,
			3
		);

		return static function () use ( &$captured ) {
			return $captured;
		};
	}

	/**
	 * Record every wpo_otd_task_updated action from now on.
	 *
	 * @return callable Returns the recorded actions, each as a list of task ID, task with fields and updated fields.
	 */
	private function record_updates(): callable {
		$recorded = array();

		add_action(
			'wpo_otd_task_updated',
			static function ( $task_id, $task, $updated_fields ) use ( &$recorded ) {
				$recorded[] = array( $task_id, $task, $updated_fields );
			},
			10,
			3
		);

		return static function () use ( &$recorded ) {
			return $recorded;
		};
	}

	/**
	 * Get the slugs of a list of options.
	 *
	 * @param array $options
	 *
	 * @return string[]
	 */
	private function slugs( array $options ): array {
		return array_map( static fn( $option ) => $option->slug, $options );
	}

	/**
	 * Assert that a callback throws an exception whose message contains the given text.
	 *
	 * @param string   $exception_class
	 * @param string   $message
	 * @param callable $callback
	 *
	 * @return void
	 */
	private function assert_throws( string $exception_class, string $message, callable $callback ): void {
		try {
			$callback();
		} catch ( \Throwable $e ) {
			$this->assertInstanceOf( $exception_class, $e );
			$this->assertStringContainsString( $message, $e->getMessage() );

			return;
		}

		$this->fail( "Expected {$exception_class} to be thrown." );
	}

	/**
	 * Make $wpdb treat a column of the tasks table as utf8mb3, which cannot store four-byte characters such as emoji.
	 *
	 * @param string $column
	 *
	 * @return void
	 */
	private function store_only_three_byte_characters_in( string $column ): void {
		$table = $this->table( 'tasks' );

		add_filter(
			'pre_get_col_charset',
			static fn( $charset, string $checked_table, string $checked_column ) => $table === $checked_table && $column === $checked_column ? 'utf8mb3' : $charset,
			10,
			3
		);
	}
}
