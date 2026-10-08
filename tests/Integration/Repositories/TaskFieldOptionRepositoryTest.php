<?php

namespace WPO\OTD\Tests\Integration\Repositories;

use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Repositories\TaskFieldOptionRepository;
use WPO\OTD\Tests\IntegrationTestCase;

class TaskFieldOptionRepositoryTest extends IntegrationTestCase {
	private TaskFieldOptionRepository $repository;

	public function set_up() {
		parent::set_up();

		$this->repository = new TaskFieldOptionRepository();
	}

	public function test_options_of_a_field_come_back_in_position_order(): void {
		global $wpdb;

		// Shuffle the stored order so the result cannot just be insertion order.
		$wpdb->update( $this->table( 'task_field_options' ), array( 'position' => 9 ), array( 'id' => $this->priority_option_id( 'low' ) ) );

		$this->assertSame(
			array( 'medium', 'high', 'critical', 'low' ),
			array_map( static fn( $option ) => $option->slug, $this->repository->get_by_field_id_ordered( DefaultTaskFields::PRIORITY ) )
		);
	}

	public function test_update_positions_numbers_the_given_order_from_one(): void {
		$done    = $this->status_option_id( 'done' );
		$todo    = $this->status_option_id( 'not_started' );
		$doing   = $this->status_option_id( 'in_progress' );

		$this->repository->update_positions( DefaultTaskFields::STATUS, array( $done, $todo, $doing ) );

		$this->assertSame( array( 'done', 'not_started', 'in_progress' ), $this->slugs( DefaultTaskFields::STATUS ) );
		$this->assertSame( array( 1, 2, 3 ), $this->stored_positions( DefaultTaskFields::STATUS ) );
	}

	public function test_update_positions_ignores_options_of_other_fields(): void {
		$this->repository->update_positions( DefaultTaskFields::STATUS, array( $this->priority_option_id( 'critical' ) ) );

		$this->assertSame( array( 'low', 'medium', 'high', 'critical' ), $this->slugs( DefaultTaskFields::PRIORITY ) );
	}

	public function test_update_positions_with_nothing_to_order_does_nothing(): void {
		$this->repository->update_positions( DefaultTaskFields::STATUS, array() );

		$this->assertSame( array( 1, 2, 3 ), $this->stored_positions( DefaultTaskFields::STATUS ) );
	}

	public function test_increment_positions_from_shifts_the_tail_of_one_field(): void {
		$this->repository->increment_positions_from( DefaultTaskFields::PRIORITY, 3 );

		$this->assertSame( array( 1, 2, 4, 5 ), $this->stored_positions( DefaultTaskFields::PRIORITY ) );
		$this->assertSame( array( 1, 2, 3 ), $this->stored_positions( DefaultTaskFields::STATUS ) );
	}

	/**
	 * Get a field's option slugs in position order.
	 *
	 * @param int $field_id
	 *
	 * @return string[]
	 */
	private function slugs( int $field_id ): array {
		return array_map( static fn( $option ) => $option->slug, $this->repository->get_by_field_id_ordered( $field_id ) );
	}

	/**
	 * Get a field's stored positions in position order.
	 *
	 * @param int $field_id
	 *
	 * @return int[]
	 */
	private function stored_positions( int $field_id ): array {
		return array_map( static fn( $option ) => $option->position, $this->repository->get_by_field_id_ordered( $field_id ) );
	}
}
