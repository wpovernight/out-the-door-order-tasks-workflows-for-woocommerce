<?php

namespace WPO\OTD\Tests\Integration\Services;

use WPO\OTD\Models\CustomOrderStatus;
use WPO\OTD\Repositories\CustomOrderStatusRepository;
use WPO\OTD\Services\CustomOrderStatusService;
use WPO\OTD\Tests\IntegrationTestCase;

class CustomOrderStatusServiceTest extends IntegrationTestCase {
	private const REASSIGN_HOOK = 'wpo_otd_reassign_orders';

	private CustomOrderStatusService $service;

	public function set_up() {
		parent::set_up();

		$this->service = new CustomOrderStatusService();
	}

	/** ================================
	 *   CRUD
	 *  ================================ */

	public function test_create_stores_the_status_and_fires_an_action(): void {
		$created = array();
		add_action(
			'wpo_otd_custom_order_status_created',
			static function ( $status ) use ( &$created ) {
				$created[] = $status;
			}
		);

		$status = $this->service->create( array( 'label' => 'Awaiting Pickup', 'background' => '#000000' ) );

		$this->assertGreaterThan( 0, $status->id );
		$this->assertSame( 'awaiting-pickup', $status->status_key );
		$this->assertSame( '#afaf96', $status->foreground );
		$this->assertSame( array( $status ), $created );
		$this->assertEquals( $status, $this->service->find( $status->id ) );
	}

	/**
	 * @dataProvider refused_create_provider
	 */
	public function test_create_refuses_a_key_it_cannot_use( array $data, string $message ): void {
		try {
			$this->service->create( $data );
			$this->fail( 'The key must be refused.' );
		} catch ( \WPO\OTD\Exceptions\InvalidStatusKeyException $e ) {
			$this->assertInstanceOf( \InvalidArgumentException::class, $e );
			$this->assertSame( $message, $e->getMessage() );
		}

		$this->assertSame( array(), $this->service->all() );
	}

	public function refused_create_provider(): array {
		$unusable = 'A status key holds up to 17 letters, digits, - and _, with at least one letter or digit.';
		$reserved = 'This status key belongs to an order status of WooCommerce or of another plugin. Choose another one.';

		return array(
			'a sent key too long'        => array( array( 'label' => 'Long', 'status_key' => str_repeat( 'a', 18 ) ), $unusable ),
			'a label without Latin text' => array( array( 'label' => 'ارسال شده' ), $unusable ),
			'a WooCommerce key sent'     => array( array( 'label' => 'Mine', 'status_key' => 'on-hold' ), $reserved ),
			'a WooCommerce key derived'  => array( array( 'label' => 'Processing' ), 'The label makes the status key processing, which belongs to an order status of WooCommerce or of another plugin. Send a status_key.' ),
		);
	}

	public function test_update_refuses_a_key_it_cannot_use_and_keeps_its_own(): void {
		$status = $this->service->create( array( 'label' => 'Packed' ) );

		foreach ( array( str_repeat( 'a', 18 ), 'on-hold', '-' ) as $key ) {
			try {
				$this->service->update( $status->id, array( 'status_key' => $key ) );
				$this->fail( "The key {$key} must be refused." );
			} catch ( \WPO\OTD\Exceptions\InvalidStatusKeyException $e ) {
				$this->assertSame( 'packed', $this->service->find( $status->id )->status_key );
			}
		}

		$this->assertSame( 'Boxed', $this->service->update( $status->id, array( 'status_key' => 'packed', 'label' => 'Boxed' ) )->label );
	}

	public function test_a_stored_status_keeps_a_key_that_is_now_refused(): void {
		$id = ( new CustomOrderStatusRepository() )->insert( array( 'status_key' => 'refunded', 'label' => 'Old refund' ) );

		$this->assertSame( 'Newer refund', $this->service->update( $id, array( 'status_key' => 'refunded', 'label' => 'Newer refund' ) )->label );
	}

	/**
	 * @dataProvider non_string_key_provider
	 */
	public function test_update_refuses_a_status_key_that_is_not_a_string( $key ): void {
		$status = $this->service->create( array( 'label' => 'Packed' ) );

		try {
			$this->service->update( $status->id, array( 'status_key' => $key, 'label' => 'Boxed' ) );
			$this->fail( 'A status key that is not a string must be refused.' );
		} catch ( \WPO\OTD\Exceptions\InvalidStatusKeyException $e ) {
			$this->assertSame( 'A status key must be a string.', $e->getMessage() );
		}

		$this->assertSame( 'packed', $this->service->find( $status->id )->status_key );
		$this->assertSame( 'Packed', $this->service->find( $status->id )->label );
	}

	public function non_string_key_provider(): array {
		return array(
			'an int'   => array( 5 ),
			'null'     => array( null ),
			'an array' => array( array( 'packed' ) ),
		);
	}

	public function test_the_unique_suffix_skips_keys_other_order_statuses_use(): void {
		add_filter( 'wc_order_statuses', static fn( $statuses ) => $statuses + array( 'wc-shipped-2' => 'Shipped elsewhere' ) );

		$this->assertSame( 'shipped', $this->service->create( array( 'label' => 'Shipped' ) )->status_key );
		$this->assertSame( 'shipped-3', $this->service->create( array( 'label' => 'Shipped' ) )->status_key );
	}

	public function test_create_makes_the_key_unique(): void {
		$this->service->create( array( 'label' => 'Packed' ) );

		$this->assertSame( 'packed-2', $this->service->create( array( 'label' => 'Packed' ) )->status_key );
		$this->assertSame( 'packed-3', $this->service->create( array( 'label' => 'Other', 'status_key' => 'packed' ) )->status_key );
	}

	public function test_all_is_refreshed_after_each_change(): void {
		$this->assertSame( array(), $this->service->all() );

		$status = $this->service->create( array( 'label' => 'Packed' ) );
		$this->assertSame( array( 'Packed' ), $this->labels( $this->service->all() ) );

		$this->service->update( $status->id, array( 'label' => 'Boxed' ) );
		$this->assertSame( array( 'Boxed' ), $this->labels( $this->service->all() ) );

		$this->service->request_deletion( $status->id );
		$this->assertSame( array(), $this->service->all() );
	}

	public function test_update_saves_and_fires_an_action(): void {
		$status = $this->service->create( array( 'label' => 'Packed' ) );
		$fired  = array();
		add_action(
			'wpo_otd_custom_order_status_updated',
			static function ( $updated, $data ) use ( &$fired ) {
				$fired[] = array( $updated->label, $data );
			},
			10,
			2
		);

		$updated = $this->service->update( $status->id, array( 'label' => 'Boxed', 'background' => '#123456' ) );

		$this->assertSame( 'Boxed', $updated->label );
		$this->assertSame( '#123456', $this->service->find( $status->id )->background );
		$this->assertSame( 'packed', $updated->status_key, 'The key stays when only the label changes.' );
		$this->assertSame( array( array( 'Boxed', array( 'label' => 'Boxed', 'background' => '#123456' ) ) ), $fired );
	}

	public function test_an_update_cannot_flag_a_status_for_deletion(): void {
		$status = $this->service->create( array( 'label' => 'Packed' ) );

		$this->service->update( $status->id, array( 'is_deleting' => true, 'label' => 'Boxed' ) );

		$this->assertFalse( $this->service->find( $status->id )->is_deleting, 'Only a deletion request flags a deletion.' );
		$this->assertSame( 'Boxed', $this->service->find( $status->id )->label );
	}

	public function test_updating_a_missing_status_is_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );

		$this->service->update( 999999, array( 'label' => 'Ghost' ) );
	}

	public function test_list_returns_everything_or_one_page(): void {
		foreach ( array( 'A', 'B', 'C' ) as $label ) {
			$this->service->create( array( 'label' => $label ) );
		}

		$full = $this->service->list();
		$this->assertSame( array( 'A', 'B', 'C' ), $this->labels( $full->items() ) );
		$this->assertSame( 3, $full->per_page() );

		$page = $this->service->list( 2, 2 );
		$this->assertSame( array( 'C' ), $this->labels( $page->items() ) );
		$this->assertSame( 3, $page->total() );

		$this->assertSame( 1, $this->service->list( 1, null )->per_page(), 'A missing page size falls back to one.' );
	}

	/** ================================
	 *   WooCommerce integration
	 *  ================================ */

	public function test_statuses_are_added_to_woocommerce_including_ones_being_deleted(): void {
		$this->service->create( array( 'label' => 'Packed <b>now</b>' ) );
		$deleting = $this->service->create( array( 'label' => 'Leaving' ) );
		$this->mark_deleting( $deleting->id );

		$statuses = $this->service->add_to_order_statuses( array( 'wc-pending' => 'Pending payment' ) );

		$this->assertSame(
			array(
				'wc-pending'   => 'Pending payment',
				'wc-packed-now' => 'Packed &lt;b&gt;now&lt;/b&gt;',
				'wc-leaving'   => 'Leaving',
			),
			$statuses
		);
	}

	public function test_statuses_are_registered_as_order_post_statuses(): void {
		$this->service->create( array( 'label' => 'Packed' ) );

		$registered = $this->service->register_order_statuses( array() );

		$this->assertSame( array( 'wc-packed' ), array_keys( $registered ) );
		$this->assertSame( 'Packed', $registered['wc-packed']['label'] );
		$this->assertTrue( $registered['wc-packed']['public'] );
		$this->assertTrue( $registered['wc-packed']['show_in_admin_status_list'] );
		$this->assertSame( 'Packed <span class="count">(%s)</span>', $registered['wc-packed']['label_count']['singular'] );
	}

	public function test_bulk_actions_go_after_the_last_mark_action_and_skip_deleting_statuses(): void {
		$this->service->create( array( 'label' => 'Packed' ) );
		$deleting = $this->service->create( array( 'label' => 'Leaving' ) );
		$this->mark_deleting( $deleting->id );

		$actions = $this->service->add_to_bulk_action(
			array(
				'edit'            => 'Edit',
				'mark_processing' => 'Change status to processing',
				'mark_completed'  => 'Change status to completed',
				'trash'           => 'Move to Trash',
			)
		);

		$this->assertSame( array( 'edit', 'mark_processing', 'mark_completed', 'mark_packed', 'trash' ), array_keys( $actions ) );
		$this->assertSame( 'Change status to Packed', $actions['mark_packed'] );
	}

	public function test_bulk_actions_are_appended_when_there_is_no_mark_action(): void {
		$this->service->create( array( 'label' => 'Packed' ) );

		$this->assertSame( array( 'trash', 'mark_packed' ), array_keys( $this->service->add_to_bulk_action( array( 'trash' => 'Move to Trash' ) ) ) );
	}

	public function test_status_colors_are_added_to_the_woocommerce_admin_styles(): void {
		wp_register_style( 'woocommerce_admin_styles', 'admin.css', array(), '1' );
		$this->service->create( array( 'label' => 'Packed', 'background' => '#000000' ) );

		$this->service->add_dynamic_style();

		$this->assertSame(
			array( 'mark.status-packed { background-color: #000000; color: #afaf96; }' ),
			wp_styles()->get_data( 'woocommerce_admin_styles', 'after' )
		);
	}

	/** ================================
	 *   Deletion
	 *  ================================ */

	public function test_requesting_deletion_of_a_missing_status_fails(): void {
		$this->assertFalse( $this->service->request_deletion( 999999 ) );
		$this->assertSame( CustomOrderStatusService::DELETION_NOT_FOUND, $this->service->begin_deletion( 999999 ) );
	}

	public function test_begin_deletion_tells_a_status_deleted_at_once_from_one_left_for_its_orders(): void {
		$empty = $this->create_registered_status( 'Empty' );
		$this->assertSame( CustomOrderStatusService::DELETION_DONE, $this->service->begin_deletion( $empty->id ) );
		$this->assertNull( $this->service->find( $empty->id ) );

		$busy = $this->create_registered_status( 'Packed' );
		$this->order_in( 'packed' );

		$this->assertSame( CustomOrderStatusService::DELETION_SCHEDULED, $this->service->begin_deletion( $busy->id ) );
		$this->assertTrue( $this->service->find( $busy->id )->is_deleting );
		$this->assertSame( CustomOrderStatusService::DELETION_SCHEDULED, $this->service->begin_deletion( $busy->id ), 'A deletion under way is still scheduled.' );
		$this->assertSame( CustomOrderStatusService::DELETION_NOT_FOUND, $this->service->begin_deletion( $empty->id ), 'A deleted status is gone.' );
	}

	public function test_a_status_without_orders_is_deleted_at_once(): void {
		$status  = $this->service->create( array( 'label' => 'Packed' ) );
		$deleted = $this->capture_deletions();

		$this->assertTrue( $this->service->request_deletion( $status->id, 'processing' ) );

		$this->assertNull( $this->service->find( $status->id ) );
		$this->assertSame( array( array( $status->id, 'packed', 'processing' ) ), $deleted() );
		$this->assertFalse( as_has_scheduled_action( self::REASSIGN_HOOK ) );
	}

	public function test_a_status_with_orders_is_flagged_and_drained_in_the_background(): void {
		$status = $this->create_registered_status( 'Packed' );
		$this->order_in( 'packed' );

		$this->assertTrue( $this->service->request_deletion( $status->id ) );

		$this->assertTrue( $this->service->find( $status->id )->is_deleting );
		$this->assertTrue( as_has_scheduled_action( self::REASSIGN_HOOK, array( 'packed', 'on-hold', $status->id ), 'wpo-otd' ) );

		$this->assertTrue( $this->service->request_deletion( $status->id ), 'A second request is accepted.' );
		$this->assertCount( 1, as_get_scheduled_actions( array( 'hook' => self::REASSIGN_HOOK, 'status' => 'pending' ), 'ids' ), '...without scheduling again.' );
	}

	public function test_a_status_the_database_refuses_to_flag_is_not_scheduled(): void {
		$status = $this->create_registered_status( 'Packed' );
		$this->order_in( 'packed' );
		$this->fail_queries( '/^UPDATE `\w+wpo_otd_custom_statuses`/' );

		try {
			$this->service->begin_deletion( $status->id );
			$this->fail( 'A status that could not be flagged must not be reported as scheduled.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( "Custom order status {$status->id} could not be flagged for deletion", $e->getMessage() );
		}

		$this->assertFalse( $this->service->find( $status->id )->is_deleting );
		$this->assertFalse( as_has_scheduled_action( self::REASSIGN_HOOK ) );
	}

	public function test_a_deletion_whose_last_batch_failed_is_scheduled_again(): void {
		$status = $this->create_registered_status( 'Packed' );
		$this->order_in( 'packed' );

		$this->assertSame( CustomOrderStatusService::DELETION_SCHEDULED, $this->service->begin_deletion( $status->id ) );

		// The job runs, moves the orders, then fails to delete the row; Action Scheduler marks it failed.
		as_unschedule_all_actions( self::REASSIGN_HOOK );
		$this->fail_queries( '/^DELETE FROM `\w+wpo_otd_custom_statuses`/' );
		try {
			$this->service->process_deletion_batch( 'packed', 'on-hold', $status->id );
			$this->fail( 'The last batch must fail when the row cannot be deleted.' );
		} catch ( \RuntimeException $e ) {
			$this->assertTrue( $this->service->find( $status->id )->is_deleting, 'The status stays flagged.' );
		}
		$this->assertFalse( as_has_scheduled_action( self::REASSIGN_HOOK ) );

		$this->assertSame( CustomOrderStatusService::DELETION_SCHEDULED, $this->service->begin_deletion( $status->id, 'processing' ) );
		$this->assertTrue( as_has_scheduled_action( self::REASSIGN_HOOK, array( 'packed', 'processing', $status->id ), 'wpo-otd' ) );

		$this->service->begin_deletion( $status->id );
		$this->assertCount( 1, as_get_scheduled_actions( array( 'hook' => self::REASSIGN_HOOK, 'status' => 'pending' ), 'ids' ), 'A pending job is not doubled, whatever its fallback.' );
	}

	public function test_a_running_job_counts_as_the_deletion_under_way(): void {
		$status = $this->create_registered_status( 'Packed' );
		$this->order_in( 'packed' );
		$this->service->begin_deletion( $status->id );

		$pending = as_get_scheduled_actions( array( 'hook' => self::REASSIGN_HOOK, 'status' => 'pending' ), 'ids' );
		$this->assertCount( 1, $pending );
		\ActionScheduler::store()->log_execution( (int) reset( $pending ) );

		$this->assertSame( CustomOrderStatusService::DELETION_SCHEDULED, $this->service->begin_deletion( $status->id ) );
		$this->assertCount( 0, as_get_scheduled_actions( array( 'hook' => self::REASSIGN_HOOK, 'status' => 'pending' ), 'ids' ), 'No second job while one runs.' );
	}

	public function test_a_job_for_the_status_counts_whatever_key_it_was_given(): void {
		$status = $this->create_registered_status( 'Packed' );
		$this->mark_deleting( $status->id );
		as_schedule_single_action( time(), self::REASSIGN_HOOK, array( 'old-key', 'processing', $status->id ), 'wpo-otd' );

		$this->assertSame( CustomOrderStatusService::DELETION_SCHEDULED, $this->service->begin_deletion( $status->id ) );
		$this->assertCount( 1, as_get_scheduled_actions( array( 'hook' => self::REASSIGN_HOOK, 'status' => 'pending' ), 'ids' ) );
	}

	public function test_a_deletion_while_another_request_only_changes_the_status_is_busy(): void {
		$status = $this->create_registered_status( 'Packed' );
		$this->order_in( 'packed' );

		$other = $this->repository_on_another_connection();
		$lock  = "custom_status_deletion_{$status->id}";

		$this->assertTrue( $other->acquire_lock( $lock, 0 ) );

		try {
			$this->assertSame( CustomOrderStatusService::DELETION_BUSY, $this->service->begin_deletion( $status->id ) );
			$this->assertFalse( $this->service->request_deletion( $status->id ), 'Nothing was deleted or scheduled.' );
			$this->assertFalse( $this->service->find( $status->id )->is_deleting, 'Nothing is flagged.' );
			$this->assertFalse( as_has_scheduled_action( self::REASSIGN_HOOK ) );
		} finally {
			$other->release_lock( $lock );
		}

		$this->assertSame( CustomOrderStatusService::DELETION_SCHEDULED, $this->service->begin_deletion( $status->id ), 'Once released, the deletion goes ahead.' );
		$this->assertTrue( as_has_scheduled_action( self::REASSIGN_HOOK, array( 'packed', 'on-hold', $status->id ), 'wpo-otd' ) );
	}

	public function test_a_deletion_while_another_request_deletes_the_status_is_scheduled_or_done(): void {
		$status = $this->service->create( array( 'label' => 'Packed' ) );
		$gone   = $this->service->create( array( 'label' => 'Gone' ) );
		$this->mark_deleting( $status->id );

		$other = $this->repository_on_another_connection();
		$this->assertTrue( $other->acquire_lock( "custom_status_deletion_{$status->id}", 0 ) );
		$this->assertTrue( $other->acquire_lock( "custom_status_deletion_{$gone->id}", 0 ) );
		( new CustomOrderStatusRepository() )->delete( $gone->id );

		try {
			$this->assertSame( CustomOrderStatusService::DELETION_SCHEDULED, $this->service->begin_deletion( $status->id ) );
			$this->assertSame( CustomOrderStatusService::DELETION_NOT_FOUND, $this->service->begin_deletion( $gone->id ) );
		} finally {
			$other->release_lock( "custom_status_deletion_{$status->id}" );
			$other->release_lock( "custom_status_deletion_{$gone->id}" );
		}

		$this->assertFalse( as_has_scheduled_action( self::REASSIGN_HOOK ), 'The request holding the lock schedules the job, not this one.' );
	}

	public function test_an_update_announces_itself_once_the_lock_is_released(): void {
		$status = $this->service->create( array( 'label' => 'Packed' ) );
		$other  = $this->repository_on_another_connection();
		$lock   = "custom_status_deletion_{$status->id}";
		$free   = null;
		add_action(
			'wpo_otd_custom_order_status_updated',
			static function () use ( $other, $lock, &$free ) {
				$free = $other->acquire_lock( $lock, 0 );
				$other->release_lock( $lock );
			}
		);

		$this->service->update( $status->id, array( 'label' => 'Boxed' ) );

		$this->assertTrue( $free, 'Another connection can take the lock while listeners run.' );
	}

	public function test_a_deletion_that_starts_while_an_update_runs_stays_under_way(): void {
		$status = $this->service->create( array( 'label' => 'Packed' ) );
		$this->flag_deleting_before_its_next_update( $status->id );

		$this->service->update( $status->id, array( 'label' => 'Boxed' ) );

		$this->assertTrue( $this->service->find( $status->id )->is_deleting, 'The update did not write back the flag it read.' );
		$this->assertSame( 'Boxed', $this->service->find( $status->id )->label );
	}

	public function test_the_deletion_lock_is_released_even_when_the_deletion_fails(): void {
		$status = $this->service->create( array( 'label' => 'Packed' ) );
		$this->fail_queries( '/^DELETE FROM `\w+wpo_otd_custom_statuses`/' );

		try {
			$this->service->begin_deletion( $status->id );
			$this->fail( 'The deletion must fail.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'could not be deleted', $e->getMessage() );
		}

		$other = $this->repository_on_another_connection();
		$lock  = "custom_status_deletion_{$status->id}";
		$this->assertTrue( $other->acquire_lock( $lock, 0 ), 'Another connection can take the lock at once.' );
		$other->release_lock( $lock );
	}

	public function test_a_deletion_batch_that_would_move_orders_to_their_own_status_stops(): void {
		$status = $this->create_registered_status( 'Packed' );
		$this->order_in( 'packed' );

		$deleted = $this->capture_deletions();
		$logged  = array();
		add_filter(
			'wpo_otd_logging_enabled',
			static function ( $enabled, $level, $text ) use ( &$logged ) {
				$logged[] = array( $level, $text );

				return false;
			},
			10,
			3
		);

		$this->service->process_deletion_batch( 'packed', 'packed', $status->id );

		$this->assertFalse( as_has_scheduled_action( self::REASSIGN_HOOK ), 'No batch is scheduled again.' );
		$this->assertSame( array(), $deleted() );
		$this->assertNotNull( $this->service->find( $status->id ) );
		$this->assertSame( 'error', $logged[0][0] );
		$this->assertStringContainsString( "The deletion of custom order status {$status->id} stopped", $logged[0][1] );
	}

	/**
	 * @dataProvider own_key_fallback_provider
	 */
	public function test_a_deletion_to_the_statuss_own_key_is_refused( string $fallback ): void {
		$status = $this->service->create( array( 'label' => 'Packed' ) );

		try {
			$this->service->begin_deletion( $status->id, $fallback );
			$this->fail( 'A status cannot move its orders to itself.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( "cannot move its orders to its own key {$fallback}", $e->getMessage() );
		}

		$this->assertFalse( $this->service->find( $status->id )->is_deleting );
		$this->assertFalse( as_has_scheduled_action( self::REASSIGN_HOOK ) );
	}

	public function own_key_fallback_provider(): array {
		return array(
			'the key'                 => array( 'packed' ),
			'the prefixed key'        => array( 'wc-packed' ),
			'the key in another case' => array( 'WC-Packed' ),
		);
	}

	public function test_a_deletion_batch_to_the_prefixed_own_key_stops(): void {
		$status = $this->create_registered_status( 'Packed' );
		$order  = $this->order_in( 'packed' );
		$logs   = $this->capture_logs();

		$this->service->process_deletion_batch( 'packed', 'wc-packed', $status->id );

		$this->assertFalse( as_has_scheduled_action( self::REASSIGN_HOOK ) );
		$this->assertSame( 'packed', wc_get_order( $order )->get_status() );
		$this->assertNotNull( $this->service->find( $status->id ) );
		$this->assertStringContainsString( 'stopped', $logs()[0][1] );
	}

	public function test_orders_the_filter_sends_back_to_their_own_status_are_skipped_without_looping(): void {
		$status = $this->create_registered_status( 'Packed' );
		$stuck  = $this->order_in( 'packed' );
		$moving = $this->order_in( 'packed' );
		$logs   = $this->capture_logs();
		add_filter(
			'wpo_otd_reassign_orders_to_status',
			static fn( $to_status, $order ) => $order->get_id() === $stuck ? 'wc-packed' : $to_status,
			10,
			2
		);

		$this->service->process_deletion_batch( 'packed', 'on-hold', $status->id );

		$this->assertSame( 'on-hold', wc_get_order( $moving )->get_status() );
		$this->assertSame( 'packed', wc_get_order( $stuck )->get_status() );
		$this->assertFalse( as_has_scheduled_action( self::REASSIGN_HOOK ), 'Only the stuck order is left, so the job stops.' );
		$this->assertNotNull( $this->service->find( $status->id ), 'The status stays while it has an order.' );

		$messages = array_column( $logs(), 1 );
		$this->assertStringContainsString( "Order {$stuck} stays in status \"packed\"", $messages[0] );
		$this->assertSame( "The deletion of custom order status {$status->id} stopped: orders {$stuck} could not be moved from packed.", end( $messages ) );
	}

	public function test_an_order_whose_save_fails_stays_and_the_job_stops(): void {
		$status = $this->create_registered_status( 'Packed' );
		$order  = $this->order_in( 'packed' );
		$logs   = $this->capture_logs();
		$refuse = static function () {
			throw new \Exception( 'Saving is refused' );
		};
		add_action( 'woocommerce_before_order_object_save', $refuse );

		$this->service->process_deletion_batch( 'packed', 'on-hold', $status->id );

		remove_action( 'woocommerce_before_order_object_save', $refuse );
		$this->assertSame( 'packed', wc_get_order( $order )->get_status() );
		$this->assertFalse( as_has_scheduled_action( self::REASSIGN_HOOK ), 'No batch is scheduled again.' );
		$this->assertNotNull( $this->service->find( $status->id ) );

		$messages = array_column( $logs(), 1 );
		$this->assertContains( "Order {$order} could not be moved from status \"packed\" to \"on-hold\"; it stays.", $messages );
		$this->assertSame( "The deletion of custom order status {$status->id} stopped: orders {$order} could not be moved from packed.", end( $messages ) );
	}

	public function test_stuck_orders_are_left_out_of_later_batches_so_older_orders_still_move(): void {
		$status = $this->create_registered_status( 'Packed' );
		$older  = $this->order_in( 'packed' );
		$first  = $this->order_in( 'packed' );
		$second = $this->order_in( 'packed' );
		$this->date_order( $older, '-2 days' );
		$this->date_order( $first, '-1 hour' );

		$logs = $this->capture_logs();
		add_filter( 'wpo_otd_reassign_orders_batch_size', static fn() => 2 );
		add_filter(
			'wpo_otd_reassign_orders_to_status',
			static fn( $to_status, $order ) => in_array( $order->get_id(), array( $first, $second ), true ) ? 'packed' : $to_status,
			10,
			2
		);

		// The newest page holds only the stuck orders: they are carried to the next batch.
		$this->service->process_deletion_batch( 'packed', 'on-hold', $status->id );

		$this->assertSame( 'packed', wc_get_order( $older )->get_status() );
		$next = as_get_scheduled_actions( array( 'hook' => self::REASSIGN_HOOK, 'status' => 'pending' ) );
		$this->assertCount( 1, $next );
		$args = reset( $next )->get_args();
		$this->assertSame( array( 'packed', 'on-hold', $status->id ), array_slice( $args, 0, 3 ) );
		$this->assertEqualsCanonicalizing( array( $first, $second ), $args[3] );

		as_unschedule_all_actions( self::REASSIGN_HOOK );
		do_action( self::REASSIGN_HOOK, ...$args );

		$this->assertSame( 'on-hold', wc_get_order( $older )->get_status(), 'The older order is reached and moved.' );
		$this->assertFalse( as_has_scheduled_action( self::REASSIGN_HOOK ), 'Only stuck orders are left, so the job stops.' );
		$this->assertNotNull( $this->service->find( $status->id ) );
		$this->assertMatchesRegularExpression(
			"/^The deletion of custom order status {$status->id} stopped: orders ({$first}, {$second}|{$second}, {$first}) could not be moved from packed\.$/",
			array_column( $logs(), 1 )[ count( $logs() ) - 1 ]
		);
	}

	public function test_a_job_scheduled_with_three_arguments_still_runs(): void {
		$status = $this->create_registered_status( 'Packed' );
		$order  = $this->order_in( 'packed' );

		do_action( self::REASSIGN_HOOK, 'packed', 'on-hold', $status->id );

		$this->assertSame( 'on-hold', wc_get_order( $order )->get_status() );
		$this->assertNull( $this->service->find( $status->id ) );
	}

	public function test_an_update_never_writes_the_deletion_flag(): void {
		$status = $this->service->create( array( 'label' => 'Packed' ) );
		$this->mark_deleting( $status->id );

		$updates = array();
		add_filter(
			'query',
			static function ( $query ) use ( &$updates ) {
				if ( preg_match( '/^UPDATE `\w+wpo_otd_custom_statuses`/', (string) $query ) ) {
					$updates[] = $query;
				}

				return $query;
			}
		);

		$this->service->update( $status->id, array( 'label' => 'Boxed', 'is_deleting' => false ) );

		$this->assertCount( 1, $updates );
		$this->assertStringNotContainsString( 'is_deleting', $updates[0] );
		$this->assertTrue( $this->service->find( $status->id )->is_deleting, 'The deletion stays under way.' );
		$this->assertSame( 'Boxed', $this->service->find( $status->id )->label );
	}

	public function test_an_update_while_another_request_holds_the_status_is_refused(): void {
		$status = $this->service->create( array( 'label' => 'Packed' ) );
		$other  = $this->repository_on_another_connection();
		$lock   = "custom_status_deletion_{$status->id}";
		$this->assertTrue( $other->acquire_lock( $lock, 0 ) );

		try {
			$this->service->update( $status->id, array( 'label' => 'Boxed' ) );
			$this->fail( 'The update must wait for the other request and then give up.' );
		} catch ( \WPO\OTD\Exceptions\StatusBeingDeletedException $e ) {
			$this->assertSame( 'Another request is changing or deleting this custom order status. Try again in a moment.', $e->getMessage() );
		} finally {
			$other->release_lock( $lock );
		}

		$this->assertSame( 'Packed', $this->service->find( $status->id )->label );
		$this->assertSame( 'Boxed', $this->service->update( $status->id, array( 'label' => 'Boxed' ) )->label, 'Once released, the update goes ahead.' );
	}

	public function test_a_status_changes_without_the_lock_when_the_database_refuses_it(): void {
		$status   = $this->service->create( array( 'label' => 'Packed' ) );
		$doomed   = $this->service->create( array( 'label' => 'Doomed' ) );
		$warnings = array();
		add_filter(
			'wpo_otd_logging_enabled',
			static function ( $enabled, $level, $text ) use ( &$warnings ) {
				if ( 'warning' === $level ) {
					$warnings[] = $text;
				}

				return false;
			},
			10,
			3
		);
		$this->fail_queries( '/GET_LOCK|RELEASE_LOCK/' );

		$this->assertSame( 'Boxed', $this->service->update( $status->id, array( 'label' => 'Boxed' ) )->label );
		$this->assertSame( CustomOrderStatusService::DELETION_DONE, $this->service->begin_deletion( $doomed->id ) );

		$this->assertNull( $this->service->find( $doomed->id ) );
		$this->assertCount( 2, $warnings );
		$this->assertStringContainsString( "Custom order status {$status->id} changes without its lock", $warnings[0] );
		$this->assertStringContainsString( "Custom order status {$doomed->id} changes without its lock", $warnings[1] );
	}

	public function test_a_status_gone_before_its_last_batch_is_not_announced_again(): void {
		$deleted = $this->capture_deletions();

		$this->service->process_deletion_batch( 'ghost', 'on-hold', 999999 );

		$this->assertSame( array(), $deleted() );
	}

	public function test_a_job_for_another_status_does_not_count_as_this_ones(): void {
		$status = $this->create_registered_status( 'Packed' );
		$this->mark_deleting( $status->id );
		as_schedule_single_action( time(), self::REASSIGN_HOOK, array( 'other', 'on-hold', $status->id + 1 ), 'wpo-otd' );

		$this->service->begin_deletion( $status->id );

		$this->assertTrue( as_has_scheduled_action( self::REASSIGN_HOOK, array( 'packed', 'on-hold', $status->id ), 'wpo-otd' ) );
	}

	public function test_a_status_the_database_refuses_to_delete_is_not_announced_as_deleted(): void {
		$status  = $this->service->create( array( 'label' => 'Packed' ) );
		$deleted = $this->capture_deletions();
		$this->fail_queries( '/^DELETE FROM `\w+wpo_otd_custom_statuses`/' );

		try {
			$this->service->begin_deletion( $status->id );
			$this->fail( 'A status that could not be deleted must not be reported as deleted.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( "Custom order status {$status->id} could not be deleted", $e->getMessage() );
		}

		$this->assertSame( array(), $deleted() );
		$this->assertNotNull( $this->service->find( $status->id ) );
	}

	public function test_the_last_batch_moves_the_orders_and_deletes_the_status(): void {
		$status  = $this->create_registered_status( 'Packed' );
		$orders  = array( $this->order_in( 'packed' ), $this->order_in( 'packed' ) );
		$deleted = $this->capture_deletions();

		$this->service->process_deletion_batch( 'packed', 'on-hold', $status->id );

		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			$this->assertSame( 'on-hold', $order->get_status() );
		}

		$this->assertNull( $this->service->find( $status->id ) );
		$this->assertSame( array( array( $status->id, 'packed', 'on-hold' ) ), $deleted() );
	}

	public function test_a_full_batch_schedules_the_next_one(): void {
		add_filter( 'wpo_otd_reassign_orders_batch_size', static fn() => 1 );
		$status = $this->create_registered_status( 'Packed' );
		$this->order_in( 'packed' );
		$this->order_in( 'packed' );

		$this->service->process_deletion_batch( 'packed', 'on-hold', $status->id );

		$this->assertNotNull( $this->service->find( $status->id ), 'The status stays until it is empty.' );
		$this->assertCount( 1, wc_get_orders( array( 'status' => 'packed', 'return' => 'ids' ) ) );
		$this->assertTrue( as_has_scheduled_action( self::REASSIGN_HOOK, array( 'packed', 'on-hold', $status->id ), 'wpo-otd' ) );
	}

	public function test_orders_still_in_the_status_after_a_batch_schedule_another(): void {
		$status = $this->create_registered_status( 'Packed' );
		$this->order_in( 'packed' );

		// An order placed in the status while the batch runs.
		$arrived = false;
		add_filter(
			'wpo_otd_reassign_orders_to_status',
			function ( $to ) use ( &$arrived ) {
				if ( ! $arrived ) {
					$arrived = true;
					$this->order_in( 'packed' );
				}

				return $to;
			}
		);

		$this->service->process_deletion_batch( 'packed', 'on-hold', $status->id );

		$this->assertNotNull( $this->service->find( $status->id ) );
		$this->assertTrue( as_has_scheduled_action( self::REASSIGN_HOOK, array( 'packed', 'on-hold', $status->id ), 'wpo-otd' ) );
	}

	public function test_the_fallback_status_can_be_filtered_per_order(): void {
		$status = $this->create_registered_status( 'Packed' );
		$first  = $this->order_in( 'packed' );
		$second = $this->order_in( 'packed' );

		add_filter(
			'wpo_otd_reassign_orders_to_status',
			static fn( $to, $order ) => $order->get_id() === $first ? 'processing' : $to,
			10,
			2
		);

		$this->service->process_deletion_batch( 'packed', 'on-hold', $status->id );

		$this->assertSame( 'processing', wc_get_order( $first )->get_status() );
		$this->assertSame( 'on-hold', wc_get_order( $second )->get_status() );
	}

	public function test_a_batch_whose_every_order_fails_to_move_is_logged_and_stops(): void {
		$status = $this->create_registered_status( 'Packed' );
		$order  = $this->order_in( 'packed' );
		$logs   = array();
		add_filter(
			'wpo_otd_logging_enabled',
			static function ( $enabled, $level, $message ) use ( &$logs ) {
				$logs[] = "{$level}: {$message}";

				return false;
			},
			10,
			3
		);
		add_filter(
			'wpo_otd_reassign_orders_to_status',
			static function () {
				throw new \RuntimeException( 'Order is locked' );
			}
		);

		$this->service->process_deletion_batch( 'packed', 'processing', $status->id );

		$this->assertSame( 'packed', wc_get_order( $order )->get_status() );
		$this->assertSame(
			array(
				"warning: Failed to reassign order {$order} from status \"packed\" to \"processing\": Order is locked",
				"error: The deletion of custom order status {$status->id} stopped: orders {$order} could not be moved from packed.",
			),
			$logs
		);
		$this->assertNotNull( $this->service->find( $status->id ), 'The status stays while orders remain.' );
		$this->assertFalse( as_has_scheduled_action( self::REASSIGN_HOOK ), 'The same failing batch is not run again; deleting the status again restarts it.' );
	}

	public function test_the_scheduled_hook_runs_a_batch(): void {
		$status = $this->create_registered_status( 'Packed' );

		do_action( self::REASSIGN_HOOK, 'packed', 'on-hold', $status->id );

		$this->assertNull( $this->service->find( $status->id ) );
	}

	/**
	 * Date an order's creation, which orders the batches newest first.
	 *
	 * @param int    $order_id
	 * @param string $when     Relative time, such as "-1 hour".
	 *
	 * @return void
	 */
	private function date_order( int $order_id, string $when ): void {
		$order = wc_get_order( $order_id );
		$order->set_date_created( strtotime( $when ) );
		$order->save();
	}

	/**
	 * Record what the plugin logs, keeping it out of WooCommerce's log files.
	 *
	 * @return callable Returns the recorded level and message pairs.
	 */
	private function capture_logs(): callable {
		$logs = array();

		add_filter(
			'wpo_otd_logging_enabled',
			static function ( $enabled, $level, $text ) use ( &$logs ) {
				$logs[] = array( $level, $text );

				return false;
			},
			10,
			3
		);

		return static function () use ( &$logs ) {
			return $logs;
		};
	}

	/**
	 * Flag a status as being deleted just before the next write to it runs, as a deletion starting in another request
	 * between an update's read and its write would.
	 *
	 * @param int $id
	 *
	 * @return void
	 */
	private function flag_deleting_before_its_next_update( int $id ): void {
		$done = false;

		add_filter(
			'query',
			static function ( $query ) use ( $id, &$done ) {
				global $wpdb;

				if ( ! $done && preg_match( '/^UPDATE `\w+wpo_otd_custom_statuses`/', (string) $query ) ) {
					$done = true;
					$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->prefix}wpo_otd_custom_statuses` SET is_deleting = 1 WHERE id = %d", $id ) );
				}

				return $query;
			}
		);
	}

	/**
	 * A status repository on a database connection of its own, as another request would have.
	 *
	 * @return CustomOrderStatusRepository
	 */
	private function repository_on_another_connection(): CustomOrderStatusRepository {
		$connection = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$connection->set_prefix( $GLOBALS['wpdb']->prefix );

		$repository = new CustomOrderStatusRepository();
		$property   = new \ReflectionProperty( $repository, 'wpdb' );
		$property->setValue( $repository, $connection );

		return $repository;
	}

	/**
	 * Create a status and make WooCommerce and WordPress recognize it, as on a real request.
	 *
	 * The plugin's own service instance caches the statuses it read earlier in the run, so it
	 * is swapped for the one under test.
	 *
	 * @param string $label
	 *
	 * @return CustomOrderStatus
	 */
	private function create_registered_status( string $label ): CustomOrderStatus {
		foreach ( array( 'wc_order_statuses', 'woocommerce_register_shop_order_post_statuses', self::REASSIGN_HOOK ) as $hook ) {
			$this->remove_plugin_callbacks( $hook );
		}

		$this->service->register_hooks();
		$status = $this->service->create( array( 'label' => $label ) );

		// Unregistered post statuses are ignored by order queries on the posts table.
		\WC_Post_Types::register_post_status();

		return $status;
	}

	/**
	 * Remove the CustomOrderStatusService callbacks the plugin hooked at boot.
	 *
	 * @param string $hook
	 *
	 * @return void
	 */
	private function remove_plugin_callbacks( string $hook ): void {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return;
		}

		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof CustomOrderStatusService ) {
					remove_filter( $hook, $callback['function'], $priority );
				}
			}
		}
	}

	/**
	 * Create an order in the given status.
	 *
	 * @param string $status
	 *
	 * @return int
	 */
	private function order_in( string $status ): int {
		$order = $this->create_order();
		$order->set_status( $status );
		$order->save();

		$this->assertSame( $status, wc_get_order( $order->get_id() )->get_status() );

		return $order->get_id();
	}

	/**
	 * Flag a status as being deleted, as request_deletion() does while orders are drained.
	 *
	 * @param int $id
	 *
	 * @return void
	 */
	private function mark_deleting( int $id ): void {
		( new CustomOrderStatusRepository() )->where( 'id', $id )->update( array( 'is_deleting' => 1 ) );
	}

	/**
	 * Record every wpo_otd_custom_order_status_deleted call.
	 *
	 * @return callable Returns the recorded argument lists.
	 */
	private function capture_deletions(): callable {
		$calls = array();

		add_action(
			'wpo_otd_custom_order_status_deleted',
			static function ( ...$args ) use ( &$calls ) {
				$calls[] = $args;
			},
			10,
			3
		);

		return static function () use ( &$calls ) {
			return $calls;
		};
	}

	/**
	 * Get the labels of a list of statuses.
	 *
	 * @param CustomOrderStatus[] $statuses
	 *
	 * @return string[]
	 */
	private function labels( array $statuses ): array {
		return array_map( static fn( $status ) => $status->label, $statuses );
	}
}
