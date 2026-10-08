<?php

namespace WPO\OTD\Tests\Integration\REST;

use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Services\TaskManagerService;
use WPO\OTD\Services\TaskStatusRoleService;

class TaskControllerTest extends RestTestCase {
	/** ================================
	 *   Permissions
	 *  ================================ */

	public function test_guests_are_turned_away(): void {
		wp_set_current_user( 0 );

		$this->assertResponse( 401, $this->request( 'GET', '/tasks' ), 'rest_forbidden' );
	}

	public function test_customers_are_turned_away(): void {
		$this->act_as( 'customer' );

		$this->assertResponse( 403, $this->request( 'GET', '/tasks' ), 'rest_forbidden' );
		$this->assertResponse( 403, $this->request( 'PUT', '/tasks/status-roles', array( 'done' => null ) ), 'rest_forbidden' );
	}

	public function test_the_permission_check_is_filterable(): void {
		$this->act_as( 'customer' );
		add_filter( 'wpo_otd_rest_api_permissions_check', '__return_true' );

		$this->assertResponse( 200, $this->request( 'GET', '/tasks' ) );
	}

	/** ================================
	 *   Tasks
	 *  ================================ */

	public function test_listing_tasks(): void {
		$this->assertSame( array( 'data' => array() ), $this->json( $this->request( 'GET', '/tasks' ) ) );

		$this->create_task( 'Listed' );
		add_filter(
			'wpo_otd_rest_prepare_tasks',
			static fn( $tasks, $request ) => array_map( static fn( $task ) => $task + array( 'extra' => $request->get_method() ), $tasks ),
			10,
			2
		);

		$data = $this->json( $this->request( 'GET', '/tasks' ) )['data'];

		$this->assertSame( 'Listed', $data[0]['title'] );
		$this->assertCount( 8, $data[0]['fields'] );
		$this->assertSame( 'GET', $data[0]['extra'] );
	}

	public function test_creating_a_task(): void {
		$response = $this->request(
			'POST',
			'/tasks',
			array(
				'title'        => 'From the API',
				'description'  => 'Body',
				'field_values' => array( $this->status( $this->status_option_id( 'in_progress' ) ) ),
			)
		);

		$this->assertResponse( 200, $response );
		$data = $this->json( $response )['data'];
		$this->assertSame( 'From the API', $data['title'] );
		$this->assertSame( 'in_progress', array_column( $data['fields'], null, 'slug' )['status']['values'][0]['resolved']['slug'] );
		$this->assertNotNull( $this->make( TaskManagerService::class )->get_task( $data['id'] ) );
	}

	/**
	 * @dataProvider invalid_task_provider
	 */
	public function test_creating_a_task_with_invalid_data( array $body, string $code, ?string $error_field ): void {
		$response = $this->request( 'POST', '/tasks', $body );

		$this->assertResponse( 400, $response, $code );

		if ( $error_field ) {
			$this->assertArrayHasKey( $error_field, $response->get_data()['data']['errors'] );
		}
		$this->assertSame( array(), $this->make( TaskManagerService::class )->get_all_tasks() );
	}

	public function invalid_task_provider(): array {
		return array(
			'missing title'         => array( array( 'description' => 'x' ), 'rest_missing_callback_param', null ),
			'title not a string'    => array( array( 'title' => array( 'x' ) ), 'rest_invalid_param', null ),
			'title too long'        => array( array( 'title' => str_repeat( 'a', 256 ) ), 'invalid_data', 'title' ),
			'field values not list' => array( array( 'title' => 'x', 'field_values' => 'status' ), 'invalid_data', 'field_values' ),
		);
	}

	public function test_reading_a_task(): void {
		$id = $this->create_task( 'Single' );
		add_filter( 'wpo_otd_rest_prepare_task', static fn( $task ) => $task + array( 'prepared' => true ) );

		$data = $this->json( $this->request( 'GET', "/tasks/{$id}" ) )['data'];

		$this->assertSame( $id, $data['id'] );
		$this->assertCount( 8, $data['fields'] );
		$this->assertTrue( $data['prepared'] );
	}

	public function test_reading_a_task_without_its_fields(): void {
		$id = $this->create_task( 'Bare' );

		$data = $this->json( $this->request( 'GET', "/tasks/{$id}", null, array( 'include_fields' => '0' ) ) )['data'];

		$this->assertSame( 'Bare', $data['title'] );
		$this->assertArrayNotHasKey( 'fields', $data );
	}

	public function test_reading_a_missing_task(): void {
		$this->assertResponse( 404, $this->request( 'GET', '/tasks/999999' ), 'not_found' );
	}

	public function test_updating_a_task(): void {
		$id = $this->create_task( 'Before' );

		$response = $this->request(
			'PUT',
			"/tasks/{$id}",
			array(
				'title'        => 'After',
				'field_values' => array( $this->status( $this->status_option_id( 'done' ) ) ),
			)
		);

		$this->assertResponse( 200, $response );
		$this->assertSame( 'After', $this->json( $response )['data']['title'] );
		$this->assertSame( (string) $this->status_option_id( 'done' ), $this->field_value( $id, DefaultTaskFields::STATUS ) );
	}

	public function test_updating_a_missing_or_invalid_task(): void {
		$id = $this->create_task( 'Valid' );

		$this->assertResponse( 404, $this->request( 'PUT', '/tasks/999999', array( 'title' => 'Ghost' ) ), 'not_found' );
		$this->assertResponse( 400, $this->request( 'PUT', "/tasks/{$id}", array( 'title' => str_repeat( 'a', 256 ) ) ), 'invalid_data' );
	}

	public function test_deleting_a_task(): void {
		$id = $this->create_task( 'Doomed' );

		$response = $this->request( 'DELETE', "/tasks/{$id}" );

		$this->assertResponse( 200, $response );
		$this->assertSame( array( 'message' => 'Task deleted successfully' ), $this->json( $response )['data'] );
		$this->assertNull( $this->make( TaskManagerService::class )->get_task( $id ) );
	}

	/** ================================
	 *   Moving, finishing, archiving
	 *  ================================ */

	public function test_moving_a_task(): void {
		$doing    = $this->status_option_id( 'in_progress' );
		$previous = $this->create_task( 'Anchor', $doing );
		$id       = $this->create_task( 'Mover' );

		$response = $this->request( 'POST', "/tasks/{$id}/move", array( 'target_status_id' => $doing, 'previous_task_id' => $previous ) );

		$this->assertResponse( 200, $response );
		$this->assertSame( array( 'new_position' => 2.0 ), $response->get_data()['data'] );
		$this->assertSame( (string) $doing, $this->field_value( $id, DefaultTaskFields::STATUS ) );
	}

	public function test_moving_a_missing_task_is_not_found(): void {
		$this->assertResponse(
			404,
			$this->request( 'POST', '/tasks/999999/move', array( 'target_status_id' => $this->status_option_id( 'in_progress' ) ) ),
			'not_found'
		);
		$this->assertSame( 0, did_action( 'wpo_otd_task_moved' ) );
	}

	public function test_a_task_update_ignores_everything_but_the_title_description_and_field_values(): void {
		$id    = $this->create_task( 'Mine' );
		$other = $this->create_task( 'Someone else' );

		$response = $this->request(
			'PUT',
			"/tasks/{$id}",
			array(
				'guarded'    => array(),
				'id'         => $other,
				'created_at' => 'not a date',
				'title'      => 'Renamed',
			)
		);

		$this->assertResponse( 200, $response );
		$this->assertSame( $id, $this->json( $response )['data']['id'] );
		$this->assertSame( 'Someone else', $this->make( TaskManagerService::class )->get_task( $other )->title );
	}

	public function test_an_option_update_cannot_move_it_to_another_field(): void {
		$option = $this->make( TaskManagerService::class )->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Review' ) );

		$response = $this->request( 'PUT', "/tasks/fields/1/options/{$option['id']}", array( 'field_id' => DefaultTaskFields::PRIORITY, 'label' => 'QA' ) );

		$this->assertSame( DefaultTaskFields::STATUS, $this->json( $response )['data']['field_id'] );
		$this->assertContains( 'QA', array_column( $this->json( $this->request( 'GET', '/tasks/fields/1/options' ) )['data'], 'label' ) );
	}

	public function test_moving_needs_a_valid_target(): void {
		$id = $this->create_task( 'Stuck' );

		$this->assertResponse( 400, $this->request( 'POST', "/tasks/{$id}/move", array() ), 'invalid_params' );
		$this->assertResponse(
			400,
			$this->request( 'POST', "/tasks/{$id}/move", array( 'target_status_id' => $this->priority_option_id( 'low' ) ) ),
			'invalid_params'
		);
	}

	public function test_finishing_a_task(): void {
		$id = $this->create_task( 'Finish me' );

		$this->assertResponse( 200, $this->request( 'POST', "/tasks/{$id}/finish" ) );
		$this->assertSame( (string) $this->status_option_id( 'done' ), $this->field_value( $id, DefaultTaskFields::STATUS ) );

		$this->assertResponse( 404, $this->request( 'POST', '/tasks/999999/finish' ), 'not_found' );
	}

	public function test_finishing_without_a_done_role_is_a_conflict(): void {
		$id = $this->create_task( 'Finish me' );
		$this->make( TaskStatusRoleService::class )->set_done_field_option_id( null );

		$this->assertResponse( 409, $this->request( 'POST', "/tasks/{$id}/finish" ), 'task_finish_failed' );
	}

	public function test_archiving_and_unarchiving(): void {
		$id = $this->create_task( 'Shelve me' );

		$this->assertResponse( 200, $this->request( 'POST', "/tasks/{$id}/archive" ) );
		$this->assertNotNull( $this->field_value( $id, DefaultTaskFields::ARCHIVED_DATE ) );

		$this->assertResponse( 200, $this->request( 'POST', "/tasks/{$id}/unarchive" ) );
		$this->assertNull( $this->field_value( $id, DefaultTaskFields::ARCHIVED_DATE ) );

		$this->assertResponse( 404, $this->request( 'POST', '/tasks/999999/archive' ), 'not_found' );
		$this->assertResponse( 404, $this->request( 'POST', '/tasks/999999/unarchive' ), 'not_found' );
	}

	/** ================================
	 *   Fields and options
	 *  ================================ */

	public function test_listing_fields_keyed_by_slug(): void {
		$data = $this->json( $this->request( 'GET', '/tasks/fields' ) )['data'];

		$this->assertSame( array( 'status', 'position', 'creator', 'order', 'due_date', 'done_date', 'archived_date', 'priority' ), array_keys( $data ) );
		$this->assertSame( 'select', $data['priority']['type'] );
	}

	public function test_listing_options_by_field_id_or_slug(): void {
		$by_id   = $this->json( $this->request( 'GET', '/tasks/fields/' . DefaultTaskFields::PRIORITY . '/options' ) )['data'];
		$by_slug = $this->json( $this->request( 'GET', '/tasks/fields/priority/options' ) )['data'];

		$this->assertSame( array( 'low', 'medium', 'high', 'critical' ), array_column( $by_id, 'slug' ) );
		$this->assertSame( $by_id, $by_slug );
	}

	public function test_listing_options_of_a_field_without_any(): void {
		$this->assertResponse( 404, $this->request( 'GET', '/tasks/fields/' . DefaultTaskFields::POSITION . '/options' ), 'not_found' );
		$this->assertResponse( 404, $this->request( 'GET', '/tasks/fields/missing/options' ), 'not_found' );
	}

	public function test_creating_an_option(): void {
		add_filter( 'wpo_otd_rest_prepare_field_option', static fn( $option ) => $option + array( 'prepared' => true ) );

		$response = $this->request( 'POST', '/tasks/fields/1/options', array( 'label' => 'Review', 'color' => '#abc', 'position' => 2 ) );

		$this->assertResponse( 200, $response );
		$data = $this->json( $response )['data'];
		$this->assertSame( 'review', $data['slug'] );
		$this->assertSame( 2, $data['position'] );
		$this->assertTrue( $data['prepared'] );
	}

	/**
	 * @dataProvider invalid_option_provider
	 */
	public function test_creating_an_invalid_option( array $body, string $code ): void {
		$this->assertResponse( 400, $this->request( 'POST', '/tasks/fields/1/options', $body ), $code );
	}

	public function invalid_option_provider(): array {
		return array(
			'missing label'    => array( array( 'color' => '#fff' ), 'invalid_data' ),
			'bad color'        => array( array( 'label' => 'X', 'color' => 'red' ), 'invalid_data' ),
			'bad slug'         => array( array( 'label' => 'X', 'slug' => 'has space' ), 'invalid_data' ),
			'position as text' => array( array( 'label' => 'X', 'position' => 'first' ), 'rest_invalid_param' ),
		);
	}

	public function test_updating_an_option(): void {
		$option = $this->make( TaskManagerService::class )->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Review' ) );

		$response = $this->request( 'PUT', "/tasks/fields/1/options/{$option['id']}", array( 'label' => 'QA' ) );

		$this->assertResponse( 200, $response );
		$this->assertSame( 'QA', $this->json( $response )['data']['label'] );
		$this->assertResponse( 404, $this->request( 'PUT', "/tasks/fields/8/options/{$option['id']}", array( 'label' => 'Moved' ) ), 'not_found' );
		$this->assertResponse( 400, $this->request( 'PUT', "/tasks/fields/1/options/{$option['id']}", array( 'color' => 'blue' ) ), 'invalid_data' );
	}

	public function test_deleting_an_option(): void {
		$option = $this->make( TaskManagerService::class )->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Review' ) );

		$this->assertResponse( 200, $this->request( 'DELETE', "/tasks/fields/1/options/{$option['id']}" ) );
		$this->assertResponse( 404, $this->request( 'DELETE', '/tasks/fields/1/options/999999' ), 'not_found' );
	}

	public function test_deleting_an_option_in_use_is_a_conflict(): void {
		$todo = $this->status_option_id( 'not_started' );
		$this->create_task( 'Uses it', $todo );

		$this->assertResponse( 409, $this->request( 'DELETE', "/tasks/fields/1/options/{$todo}" ), 'delete_failed' );
		$this->assertResponse( 409, $this->request( 'DELETE', '/tasks/fields/1/options/' . $this->status_option_id( 'done' ) ), 'delete_failed' );
	}

	public function test_reordering_options(): void {
		$ids = array( $this->status_option_id( 'done' ), $this->status_option_id( 'in_progress' ), $this->status_option_id( 'not_started' ) );

		$this->assertResponse( 200, $this->request( 'POST', '/tasks/fields/1/options/reorder', array( 'ordered_option_ids' => $ids ) ) );
		$this->assertSame( array( 'done', 'in_progress', 'not_started' ), array_column( $this->json( $this->request( 'GET', '/tasks/fields/1/options' ) )['data'], 'slug' ) );

		$this->assertResponse( 400, $this->request( 'POST', '/tasks/fields/1/options/reorder', array( 'ordered_option_ids' => array( $ids[0] ) ) ), 'invalid_data' );
		$this->assertResponse( 400, $this->request( 'POST', '/tasks/fields/1/options/reorder', array( 'ordered_option_ids' => array() ) ), 'rest_invalid_param' );
		$this->assertResponse( 400, $this->request( 'POST', '/tasks/fields/1/options/reorder', array() ), 'rest_missing_callback_param' );
	}

	/** ================================
	 *   Status roles
	 *  ================================ */

	public function test_updating_status_roles(): void {
		$todo = $this->status_option_id( 'not_started' );

		$response = $this->request( 'PUT', '/tasks/status-roles', array( 'undone' => $todo ) );

		$this->assertResponse( 200, $response );
		$this->assertSame( array( 'done' => $this->status_option_id( 'done' ), 'undone' => $todo ), $this->json( $response )['data'], 'Roles left out are untouched.' );

		$cleared = $this->json( $this->request( 'PUT', '/tasks/status-roles', array( 'done' => null ) ) )['data'];
		$this->assertNull( $cleared['done'] );
	}

	public function test_updating_status_roles_with_invalid_values(): void {
		$this->assertResponse( 400, $this->request( 'PUT', '/tasks/status-roles', array( 'done' => 'abc' ) ), 'invalid_data' );
		$this->assertResponse( 400, $this->request( 'PUT', '/tasks/status-roles', array( 'done' => $this->priority_option_id( 'low' ) ) ), 'invalid_option' );
	}

	/**
	 * Create a task through the service and return its ID.
	 *
	 * @param string   $title
	 * @param int|null $status_id Defaults to "not started".
	 *
	 * @return int
	 */
	private function create_task( string $title, ?int $status_id = null ): int {
		return $this->make( TaskManagerService::class )->create_task(
			$title,
			'',
			array( $this->status( $status_id ?? $this->status_option_id( 'not_started' ) ) )
		)['id'];
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
}
