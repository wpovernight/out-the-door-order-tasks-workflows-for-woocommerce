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

	public function test_a_task_created_before_a_later_step_failed_is_reported_with_its_id(): void {
		add_filter( 'wpo_otd_logging_enabled', '__return_false' );
		add_action(
			'wpo_otd_task_created',
			static function () {
				throw new \RuntimeException( 'Listener failed' );
			}
		);

		$response = $this->request( 'POST', '/tasks', array( 'title' => 'Half done' ) );

		$this->assertResponse( 500, $response, 'task_created_with_error' );
		$error = $this->json( $response );
		$this->assertSame( 'The task was created, but a step after its creation failed.', $error['message'] );
		$this->assertSame( 'Half done', $this->make( TaskManagerService::class )->get_task( $error['data']['task_id'] )->title );
	}

	public function test_a_failing_response_filter_reports_the_created_task_with_its_id(): void {
		add_filter( 'wpo_otd_logging_enabled', '__return_false' );
		add_filter(
			'wpo_otd_rest_prepare_task',
			static function () {
				throw new \RuntimeException( 'Filter failed' );
			}
		);

		$response = $this->request( 'POST', '/tasks', array( 'title' => 'Filtered' ) );

		$this->assertResponse( 500, $response, 'task_created_with_error' );
		$this->assertSame( 'Filtered', $this->make( TaskManagerService::class )->get_task( $this->json( $response )['data']['task_id'] )->title );
	}

	public function test_a_task_that_could_not_be_created_is_reported_without_an_id(): void {
		add_filter( 'wpo_otd_logging_enabled', '__return_false' );
		$this->throw_on_queries( '/^INSERT INTO \S*task_field_values /', new \LogicException( 'Database unavailable' ) );

		$response = $this->request( 'POST', '/tasks', array( 'title' => 'Never kept' ) );

		$this->assertResponse( 500, $response, 'task_creation_failed' );
		$this->assertArrayNotHasKey( 'task_id', $this->json( $response )['data'] );
		$this->assertSame( array(), $this->make( TaskManagerService::class )->get_all_tasks() );
	}

	public function test_creating_a_task_titled_zero(): void {
		$response = $this->request( 'POST', '/tasks', array( 'title' => '0' ) );

		$this->assertResponse( 200, $response );
		$this->assertSame( '0', $this->json( $response )['data']['title'] );
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
			'empty title'           => array( array( 'title' => '' ), 'invalid_data', 'title' ),
			'title too long'        => array( array( 'title' => str_repeat( 'a', 256 ) ), 'invalid_data', 'title' ),
			'title of markup alone' => array( array( 'title' => '<b></b>' ), 'invalid_data', 'title' ),
			'title of whitespace'   => array( array( 'title' => " \n\t " ), 'invalid_data', 'title' ),
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

	/**
	 * @dataProvider include_fields_provider
	 */
	public function test_include_fields_is_read_as_a_boolean( ?string $value, bool $included ): void {
		$id = $this->create_task( 'Bare' );

		$query    = null === $value ? array() : array( 'include_fields' => $value );
		$response = $this->request( 'GET', "/tasks/{$id}", null, $query );

		$this->assertResponse( 200, $response );
		$data = $this->json( $response )['data'];
		$this->assertSame( 'Bare', $data['title'] );
		$this->assertSame( $included, array_key_exists( 'fields', $data ) );
	}

	public function include_fields_provider(): array {
		return array(
			'absent'  => array( null, true ),
			'"true"'  => array( 'true', true ),
			'"1"'     => array( '1', true ),
			'"false"' => array( 'false', false ),
			'"FALSE"' => array( 'FALSE', false ),
			'"0"'     => array( '0', false ),
		);
	}

	/**
	 * @dataProvider invalid_include_fields_provider
	 */
	public function test_include_fields_that_is_not_a_boolean_is_refused( string $value ): void {
		$id = $this->create_task( 'Bare' );

		$this->assertResponse( 400, $this->request( 'GET', "/tasks/{$id}", null, array( 'include_fields' => $value ) ), 'rest_invalid_param' );
	}

	public function invalid_include_fields_provider(): array {
		return array(
			'a word'   => array( 'maybe' ),
			'"2"'      => array( '2' ),
			'"yes"'    => array( 'yes' ),
			'an empty' => array( '' ),
		);
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

	public function test_a_status_update_is_announced_whatever_slug_is_sent_with_it(): void {
		$todo     = $this->status_option_id( 'not_started' );
		$done     = $this->status_option_id( 'done' );
		$id       = $this->create_task( 'Before' );
		$reported = null;
		add_action(
			'wpo_otd_task_updated',
			static function ( $task_id, $task, $updated_fields ) use ( &$reported ) {
				$reported = $updated_fields;
			},
			10,
			3
		);

		$response = $this->request(
			'PUT',
			"/tasks/{$id}",
			array( 'field_values' => array( array( 'field_id' => DefaultTaskFields::STATUS, 'field_slug' => 'priority', 'value' => $done ) ) )
		);

		$this->assertResponse( 200, $response );
		$this->assertSame( array( 'status' => array( 'old_value' => (string) $todo, 'new_value' => $done ) ), $reported );
		$this->assertNotNull( $this->field_value( $id, DefaultTaskFields::DONE_DATE ) );
	}

	public function test_updating_a_missing_or_invalid_task(): void {
		$id = $this->create_task( 'Valid' );

		$this->assertResponse( 404, $this->request( 'PUT', '/tasks/999999', array( 'title' => 'Ghost' ) ), 'not_found' );
		$this->assertResponse( 400, $this->request( 'PUT', "/tasks/{$id}", array( 'title' => str_repeat( 'a', 256 ) ) ), 'invalid_data' );
	}

	public function test_updating_a_task_with_a_title_empty_once_sanitized_is_refused(): void {
		$id = $this->create_task( 'Valid' );

		$response = $this->request( 'PUT', "/tasks/{$id}", array( 'title' => '<b> </b>' ) );

		$this->assertResponse( 400, $response, 'invalid_data' );
		$this->assertSame(
			array( 'The title field is empty once its markup and whitespace are removed.' ),
			$response->get_data()['data']['errors']['title']
		);
		$this->assertSame( 'Valid', $this->make( TaskManagerService::class )->get_task( $id )->title );
	}

	public function test_deleting_a_task(): void {
		$id = $this->create_task( 'Doomed' );

		$response = $this->request( 'DELETE', "/tasks/{$id}" );

		$this->assertResponse( 200, $response );
		$this->assertSame( array( 'message' => 'Task deleted successfully' ), $this->json( $response )['data'] );
		$this->assertNull( $this->make( TaskManagerService::class )->get_task( $id ) );
	}

	public function test_deleting_a_missing_task_is_not_found(): void {
		$deleted = array();
		add_action(
			'wpo_otd_task_deleted',
			static function ( $task_id ) use ( &$deleted ) {
				$deleted[] = $task_id;
			}
		);

		$this->assertResponse( 404, $this->request( 'DELETE', '/tasks/999999' ), 'not_found' );
		$this->assertSame( array(), $deleted );

		$id = $this->create_task( 'Doomed' );
		$this->assertResponse( 200, $this->request( 'DELETE', "/tasks/{$id}" ) );
		$this->assertResponse( 404, $this->request( 'DELETE', "/tasks/{$id}" ), 'not_found' );
	}

	public function test_a_task_the_database_refuses_to_delete_is_a_server_error(): void {
		$id = $this->create_task( 'Stays' );
		$this->fail_queries( '/^DELETE FROM `\w+wpo_otd_tasks`/' );

		$this->assertResponse( 500, $this->request( 'DELETE', "/tasks/{$id}" ), 'task_deletion_failed' );
		$this->assertNotNull( $this->make( TaskManagerService::class )->get_task( $id ) );
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

	public function test_an_option_the_database_refuses_to_delete_is_a_server_error(): void {
		$option = $this->make( TaskManagerService::class )->add_field_option( DefaultTaskFields::STATUS, array( 'label' => 'Review' ) );
		$this->fail_queries( '/^DELETE FROM `\w+wpo_otd_task_field_options`/' );

		$response = $this->request( 'DELETE', "/tasks/fields/1/options/{$option['id']}" );

		$this->assertResponse( 500, $response, 'deletion_failed' );
		$this->assertSame( 'Failed to delete field option.', $response->get_data()['message'] );
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
	 * @dataProvider loose_status_role_provider
	 */
	public function test_a_status_role_must_be_a_json_integer( string $role, string $json ): void {
		$roles  = $this->make( TaskStatusRoleService::class );
		$before = array( $roles->get_done_field_option_id(), $roles->get_undone_field_option_id() );

		// Sent as written, since encoding a PHP float may drop its fraction.
		$request = new \WP_REST_Request( 'PUT', self::NAMESPACE . '/tasks/status-roles' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( "{\"{$role}\": {$json}}" );
		$response = rest_do_request( $request );

		$this->assertResponse( 400, $response, 'invalid_data' );
		$this->assertSame( array( "The {$role} field must be an integer." ), $response->get_data()['data']['errors'][ $role ] );
		$this->assertSame( $before, array( $roles->get_done_field_option_id(), $roles->get_undone_field_option_id() ), 'No role changes.' );
	}

	public function loose_status_role_provider(): array {
		return array(
			'done as true'               => array( 'done', 'true' ),
			'undone as true'             => array( 'undone', 'true' ),
			'done as false'              => array( 'done', 'false' ),
			'done as a numeric string'   => array( 'done', '"5"' ),
			'undone as a numeric string' => array( 'undone', '"1"' ),
			'done as a float'            => array( 'done', '5.0' ),
			'done as an array'           => array( 'done', '[5]' ),
		);
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
