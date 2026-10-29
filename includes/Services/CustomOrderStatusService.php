<?php

namespace WPO\OTD\Services;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use WPO\OTD\Exceptions\InvalidStatusKeyException;
use WPO\OTD\Exceptions\StatusBeingDeletedException;
use WPO\OTD\Exceptions\StatusKeyTakenException;
use WPO\OTD\Models\CustomOrderStatus;
use WPO\OTD\Core\Logger;
use WPO\OTD\Repositories\CustomOrderStatusRepository;
use WPO\OTD\Utilities\Paginator;

defined( 'ABSPATH' ) || exit;

class CustomOrderStatusService {
	/**
	 * What begin_deletion() did: no status had the ID.
	 */
	public const DELETION_NOT_FOUND = 'not_found';

	/**
	 * What begin_deletion() did: the status had no orders and is deleted.
	 */
	public const DELETION_DONE = 'deleted';

	/**
	 * What begin_deletion() did: the status stays until its orders are moved to the fallback status in the
	 * background, or was already waiting for that.
	 */
	public const DELETION_SCHEDULED = 'scheduled';

	/**
	 * What begin_deletion() did: nothing, since another request was changing the status, which is not being deleted.
	 */
	public const DELETION_BUSY = 'busy';

	/**
	 * Seconds a change or deletion of a status waits while another request changes or deletes it.
	 */
	private const DELETION_LOCK_TIMEOUT = 3;

	/**
	 * WooCommerce's own order status keys, refused even before WooCommerce lists them.
	 */
	private const WOOCOMMERCE_STATUS_KEYS = array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed', 'checkout-draft' );

	protected readonly CustomOrderStatusRepository $repository;

	/**
	 * @var CustomOrderStatus[]|null
	 */
	private ?array $cached_statuses = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new CustomOrderStatusRepository();
	}

	/**
	 * Register hooks and filters.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Add custom order statuses to WooCommerce.
		add_filter( 'wc_order_statuses', array( $this, 'add_to_order_statuses' ) );

		// Register custom order statuses with WooCommerce so they are recognized as valid statuses.
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( $this, 'register_order_statuses' ) );

		// Add custom order statuses to the bulk actions dropdown in the orders list table.
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_to_bulk_action' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_to_bulk_action' ) ); // HPOS support

		// Add dynamic styles for custom order statuses in the orders page.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_dynamic_style' ), 99 );

		// Drain & finalize a custom status deletion, one batch per scheduled run.
		add_action( 'wpo_otd_reassign_orders', array( $this, 'process_deletion_batch' ), 10, 3 );
	}

	/** ================================
	 *   Integration with WooCommerce
	 *  ================================ */

	/**
	 * Add custom order statuses to the list of WooCommerce order statuses.
	 *
	 * @param array $order_statuses
	 *
	 * @return array
	 */
	public function add_to_order_statuses( array $order_statuses ): array {
		$statuses = $this->all();

		foreach ( $statuses as $status ) {
			// Statuses pending deletion are intentionally NOT skipped: this filter
			// is also WooCommerce's label map (wc_get_order_status_name()), so
			// dropping one would make orders still sitting in it — while the drain
			// is in progress — render a raw "wc-foo" key instead of their label.

			$order_statuses[ $status->get_prefixed_status_key() ] = esc_html( $status->label );
		}

		return $order_statuses;
	}

	/**
	 * Register custom order statuses with WooCommerce.
	 *
	 * @param array $order_statuses
	 *
	 * @return array
	 */
	public function register_order_statuses( array $order_statuses ): array {
		$statuses = $this->all();

		foreach ( $statuses as $status ) {
			$label = esc_html( $status->label );

			$order_statuses[ $status->get_prefixed_status_key() ] = array(
				'label'                     => $label,
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count' => array(
					'singular' => $label . ' <span class="count">(%s)</span>',
					'plural'   => $label . ' <span class="count">(%s)</span>',
					'context'  => null,
					'domain'   => null,
				),
			);
		}

		return $order_statuses;
	}

	/**
	 * Add custom order statuses to bulk actions dropdown.
	 *
	 * @param array $bulk_actions
	 *
	 * @return array
	 */
	public function add_to_bulk_action( array $bulk_actions ): array {
		$statuses         = $this->all();
		$new_bulk_actions = array();

		foreach ( $statuses as $status ) {
			// Skip "deleting" statuses, as they are not actionable.
			if ( $status->is_deleting ) {
				continue;
			}

			$new_bulk_actions[ 'mark_' . $status->status_key ] = sprintf(
				/* translators: %s: status label */
				__( 'Change status to %s', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
				esc_html( $status->label )
			);
		}

		// Insert new bulk actions after the default "Mark as completed/pending/etc." actions.
		$keys            = array_keys( $bulk_actions );
		$insert_position = count( $keys );
		foreach ( $keys as $index => $key ) {
			if ( str_starts_with( $key, 'mark_' ) ) {
				$insert_position = $index + 1;
			}
		}

		return array_slice( $bulk_actions, 0, $insert_position, true )
			+ $new_bulk_actions
			+ array_slice( $bulk_actions, $insert_position, null, true );
	}

	/**
	 * Add dynamic styles for custom order statuses colors.
	 *
	 * @return void
	 */
	public function add_dynamic_style(): void {
		$statuses   = $this->all();
		$custom_css = '';

		foreach ( $statuses as $status ) {
			$custom_css .= sprintf(
				'mark.status-%s { background-color: %s; color: %s; }',
				esc_attr( $status->status_key ),
				esc_attr( $status->background ),
				esc_attr( $status->foreground )
			);
		}

		wp_add_inline_style( 'woocommerce_admin_styles', $custom_css );
	}

	/** ================================
	 *   CRUD Operations
	 *  ================================ */

	/**
	 * Return all statuses.
	 *
	 * @return CustomOrderStatus[]
	 */
	public function all(): array {
		if ( null === $this->cached_statuses ) {
			$this->cached_statuses = $this->repository->get();
		}

		return $this->cached_statuses;
	}

	/**
	 * List statuses, optionally paginated.
	 *
	 * @param int|null $page     Page number (1-based), or null for the full list.
	 * @param int|null $per_page Items per page (required when $page is provided).
	 *
	 * @return Paginator
	 */
	public function list( ?int $page = null, ?int $per_page = null ): Paginator {
		// A null page means "no pagination requested", return the full list on a single page.
		if ( null === $page ) {
			return Paginator::full( $this->all() );
		}

		return $this->repository->paginate( max( 1, (int) $per_page ), $page );
	}

	/**
	 * Get a custom status by ID.
	 *
	 * @param int $id
	 *
	 * @return CustomOrderStatus|null
	 */
	public function find( int $id ): ?CustomOrderStatus {
		return $this->repository->find( $id );
	}

	/**
	 * Why a custom order status cannot take a status key, or null when it can.
	 *
	 * A key must fit WooCommerce's status columns and hold a letter or digit, and must not be the key of an order
	 * status of WooCommerce or of another plugin: the status would take that status's place, and deleting it would
	 * move that status's orders.
	 *
	 * @param string $key The key as it is stored, sanitized.
	 *
	 * @return string|null A message for the user.
	 */
	public function key_problem( string $key ): ?string {
		if ( ! CustomOrderStatus::is_usable_key( $key ) ) {
			return sprintf(
				/* translators: %d: the longest status key, in characters. */
				__( 'A status key holds up to %d letters, digits, - and _, with at least one letter or digit.', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
				CustomOrderStatus::MAX_KEY_LENGTH
			);
		}

		if ( in_array( strtolower( $key ), $this->reserved_keys(), true ) ) {
			return __( 'This status key belongs to an order status of WooCommerce or of another plugin. Choose another one.', 'out-the-door-order-tasks-workflows-for-woocommerce' );
		}

		return null;
	}

	/**
	 * The keys of the order statuses this plugin does not own, without their "wc-" prefix.
	 *
	 * @return string[]
	 */
	private function reserved_keys(): array {
		$keys = self::WOOCOMMERCE_STATUS_KEYS;

		if ( function_exists( 'wc_get_order_statuses' ) ) {
			foreach ( array_keys( wc_get_order_statuses() ) as $prefixed ) {
				$keys[] = strtolower( str_starts_with( (string) $prefixed, 'wc-' ) ? substr( (string) $prefixed, 3 ) : (string) $prefixed );
			}
		}

		$own = array_map( static fn( CustomOrderStatus $status ) => strtolower( $status->status_key ), $this->all() );

		// WooCommerce's own keys stay reserved even when a status of this plugin took one of them before.
		return array_values( array_unique( array_merge( self::WOOCOMMERCE_STATUS_KEYS, array_diff( $keys, $own ) ) ) );
	}

	/**
	 * Create a new custom order status.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return CustomOrderStatus
	 * @throws InvalidStatusKeyException If the status key, sent or made from the label, cannot be used.
	 * @throws Exception
	 */
	public function create( array $data ): CustomOrderStatus {
		$status = new CustomOrderStatus( $data );

		$problem = $this->key_problem( $status->status_key );
		if ( null !== $problem ) {
			// A key made from the label is one the user never typed: name it.
			if ( empty( $data['status_key'] ) && CustomOrderStatus::is_usable_key( $status->status_key ) ) {
				$problem = sprintf(
					/* translators: %s: the status key made from the label. */
					__( 'The label makes the status key %s, which belongs to an order status of WooCommerce or of another plugin. Send a status_key.', 'out-the-door-order-tasks-workflows-for-woocommerce' ),
					$status->status_key
				);
			}

			throw new InvalidStatusKeyException( esc_html( $problem ) );
		}

		// Ensure the status key is unique.
		$existing = $this->repository->where( 'status_key', $status->status_key )->first();
		if ( $existing ) {
			// Append a number to make it unique, cutting the key so it still fits.
			$base_key = $status->status_key;
			$counter  = 2;
			do {
				$suffix             = '-' . $counter;
				$status->status_key = CustomOrderStatus::shorten_key( $base_key, CustomOrderStatus::MAX_KEY_LENGTH - strlen( $suffix ) ) . $suffix;
				$existing           = $this->repository->where( 'status_key', $status->status_key )->first();
				$counter++;
			} while ( $existing );
		}

		$result = $this->repository->save( $status );

		if ( ! $result ) {
			$db_error = $this->repository->get_last_db_error();
			throw new Exception(
				esc_html__( 'Failed to create custom order status', 'out-the-door-order-tasks-workflows-for-woocommerce' )
				. ( $db_error ? ' Database error: ' . esc_html( $db_error ) : '' )
			);
		}

		$status->id            = $result;
		$this->cached_statuses = null;

		/**
		 * Action triggered after a custom order status is created.
		 *
		 * @param CustomOrderStatus $status The newly created custom order status.
		 */
		do_action( 'wpo_otd_custom_order_status_created', $status );

		return $status;
	}

	/**
	 * Update a custom order status by ID.
	 *
	 * @param int $id
	 * @param array<string, mixed> $data
	 *
	 * @return CustomOrderStatus
	 * @throws InvalidArgumentException If no status has the ID.
	 * @throws InvalidStatusKeyException If the status key in $data cannot be used.
	 * @throws StatusKeyTakenException If another status already uses the status key in $data.
	 * @throws StatusBeingDeletedException If $data changes the status key of a status being deleted, or another
	 *                                     request deleting or changing the status holds it too long.
	 * @throws RuntimeException If the database does not save the change.
	 */
	public function update( int $id, array $data ): CustomOrderStatus {
		[ $custom_status, $data ] = $this->with_status_lock(
			$id,
			fn() => $this->update_locked( $id, $data ),
			static function (): never {
				throw new StatusBeingDeletedException( esc_html__( 'Another request is changing or deleting this custom order status. Try again in a moment.', 'out-the-door-order-tasks-workflows-for-woocommerce' ) );
			}
		);

		// Announced once the lock is released, so a listener that changes or deletes the status is not kept waiting.

		/**
		 * Action triggered after a custom order status is updated.
		 *
		 * @param CustomOrderStatus $custom_status  The updated custom order status.
		 * @param array             $data           The data used for the update.
		 */
		do_action( 'wpo_otd_custom_order_status_updated', $custom_status, $data );

		return $custom_status;
	}

	/**
	 * The change update() makes, run while it holds the status's lock.
	 *
	 * @param int                  $id
	 * @param array<string, mixed> $data
	 *
	 * @return array{0: CustomOrderStatus, 1: array<string, mixed>} The updated status, and the data as applied.
	 * @throws InvalidArgumentException|InvalidStatusKeyException|StatusKeyTakenException|StatusBeingDeletedException|RuntimeException As update() does.
	 */
	private function update_locked( int $id, array $data ): array {
		$custom_status = $this->repository->find( $id );
		if ( ! $custom_status ) {
			throw new InvalidArgumentException( esc_html__( 'Custom order status not found', 'out-the-door-order-tasks-workflows-for-woocommerce' ) );
		}

		// Keys are stored as create() stores them, so the uniqueness check compares like with like.
		if ( isset( $data['status_key'] ) && is_string( $data['status_key'] ) ) {
			$data['status_key'] = sanitize_title( $data['status_key'] );

			// The background job moving its orders finds them by the key it was given.
			if ( $custom_status->is_deleting && $data['status_key'] !== $custom_status->status_key ) {
				throw new StatusBeingDeletedException( esc_html__( 'This custom order status is being deleted, so its status key cannot change.', 'out-the-door-order-tasks-workflows-for-woocommerce' ) );
			}

			// A key it holds already stays, as stored.
			$problem = $data['status_key'] === $custom_status->status_key ? null : $this->key_problem( $data['status_key'] );
			if ( null !== $problem ) {
				throw new InvalidStatusKeyException( esc_html( $problem ) );
			}

			$taken = $this->repository
				->where( 'status_key', $data['status_key'] )
				->where( 'id', '!=', $id )
				->first();

			if ( $taken ) {
				throw new StatusKeyTakenException( esc_html__( 'Another custom order status already uses this status key.', 'out-the-door-order-tasks-workflows-for-woocommerce' ) );
			}
		}

		$custom_status->fill( $data );

		// is_deleting is written by a deletion alone: writing back the value read here could undo one.
		$columns = array_diff_key( $custom_status->to_db_array(), array( 'is_deleting' => true ) );
		$result  = $this->repository->where( 'id', $id )->update( $columns );

		if ( false === $result ) {
			$db_error = $this->repository->get_last_db_error();
			throw new RuntimeException(
				esc_html__( 'Failed to update custom order status', 'out-the-door-order-tasks-workflows-for-woocommerce' )
				. ( $db_error ? ' Database error: ' . esc_html( $db_error ) : '' )
			);
		}

		$this->cached_statuses = null;

		return array( $custom_status, $data );
	}

	/**
	 * Request deletion of a custom order status.
	 *
	 * A status no order has is deleted at once. One that orders still have is flagged is_deleting and its orders are
	 * moved to the fallback status by a background batch job; the row is deleted once the final batch completes (see
	 * process_deletion_batch()). Until then the status stays registered, so wc_get_orders() can keep matching its
	 * orders. begin_deletion() does the same and tells which of the two happened.
	 *
	 * @param int    $id              The custom status ID to delete.
	 * @param string $fallback_status The status to assign to affected orders (default: 'on-hold').
	 *
	 * @return bool True if the status was deleted or its deletion scheduled, false if the status was not found.
	 * @throws RuntimeException If the database does not delete or flag the status.
	 */
	public function request_deletion( int $id, string $fallback_status = 'on-hold' ): bool {
		return self::DELETION_NOT_FOUND !== $this->begin_deletion( $id, $fallback_status );
	}

	/**
	 * Delete a custom order status at once, or schedule its deletion when orders still have it.
	 *
	 * Works as request_deletion() does, and tells which of the two happened.
	 *
	 * @param int    $id              The custom status ID to delete.
	 * @param string $fallback_status The status to assign to affected orders (default: 'on-hold').
	 *
	 * @return string self::DELETION_DONE when the status is deleted, self::DELETION_SCHEDULED when it stays until
	 *                its orders are moved (also when that was already under way), self::DELETION_NOT_FOUND when no
	 *                status has the ID. While another request holds the status too long, as it read then: deleted
	 *                or scheduled when the status is gone or being deleted, or self::DELETION_BUSY when another
	 *                request is only changing it, and nothing is deleted.
	 * @throws RuntimeException If the database does not delete or flag the status.
	 */
	public function begin_deletion( int $id, string $fallback_status = 'on-hold' ): string {
		// Two requests deleting the same status at once would both schedule a job.
		return $this->with_status_lock(
			$id,
			fn() => $this->delete_or_schedule( $id, $fallback_status ),
			fn() => $this->deletion_outcome_while_busy( $id )
		);
	}

	/**
	 * What a deletion that could not take the status's lock tells, from the status as it reads now.
	 *
	 * The request holding the lock may be deleting the status, which makes this deletion's work done or scheduled, or
	 * only changing it, which leaves it to be deleted again.
	 *
	 * @param int $id
	 *
	 * @return string One of the DELETION_* outcomes.
	 */
	private function deletion_outcome_while_busy( int $id ): string {
		$status = $this->find( $id );

		if ( ! $status ) {
			return self::DELETION_NOT_FOUND;
		}

		return $status->is_deleting ? self::DELETION_SCHEDULED : self::DELETION_BUSY;
	}

	/**
	 * Run a change to a custom order status while holding the status's lock, so changes and deletions of it cannot
	 * interleave.
	 *
	 * When the database refuses the lock outright, rather than waiting for it, the change runs without it, which is
	 * logged: a server that refuses named locks would otherwise block every change.
	 *
	 * @template T
	 * @param int           $id
	 * @param callable(): T $callback  The change.
	 * @param callable(): T $when_busy Run instead when another request still holds the lock after the timeout.
	 *
	 * @return T
	 */
	private function with_status_lock( int $id, callable $callback, callable $when_busy ): mixed {
		$lock   = "custom_status_deletion_{$id}";
		$locked = $this->repository->acquire_lock( $lock, self::DELETION_LOCK_TIMEOUT );

		if ( null === $locked ) {
			Logger::warning( "Custom order status {$id} changes without its lock, which the database refused: " . $this->repository->get_last_db_error() );

			return $callback();
		}

		if ( ! $locked ) {
			return $when_busy();
		}

		try {
			return $callback();
		} finally {
			$this->repository->release_lock( $lock );
		}
	}

	/**
	 * The deletion begin_deletion() starts, run while it holds the status's lock.
	 *
	 * @param int    $id
	 * @param string $fallback_status
	 *
	 * @return string One of the DELETION_* outcomes.
	 * @throws RuntimeException If the database does not delete or flag the status.
	 */
	private function delete_or_schedule( int $id, string $fallback_status ): string {
		$status = $this->find( $id );
		if ( ! $status ) {
			return self::DELETION_NOT_FOUND;
		}

		// Its orders would be moved to the status they have, again and again.
		if ( $status->status_key === $fallback_status ) {
			throw new RuntimeException( esc_html( "Custom order status {$id} cannot move its orders to its own key {$fallback_status}." ) );
		}

		if ( $status->is_deleting ) {
			// A batch job that failed leaves the status flagged with nothing left to finish its deletion.
			if ( ! $this->has_deletion_job( $id ) ) {
				as_schedule_single_action(
					time(),
					'wpo_otd_reassign_orders',
					array( $status->status_key, $fallback_status, $id ),
					'wpo-otd'
				);
			}

			return self::DELETION_SCHEDULED;
		}

		$existing_orders = wc_get_orders(
			array(
				'status' => $status->status_key,
				'limit'  => 1,
				'return' => 'ids',
			)
		);

		// Nothing to drain — delete the status immediately.
		if ( 0 === count( $existing_orders ) ) {
			$this->remove( $id, $status->status_key, $fallback_status );

			return self::DELETION_DONE;
		}

		// Orders exist: mark as pending deletion and schedule the async drain.
		// Do NOT delete the row yet — it must stay registered so wc_get_orders()
		// can still match its orders.
		$status->is_deleting = true;
		if ( false === $this->repository->save( $status ) ) {
			throw new RuntimeException( esc_html( "Custom order status {$id} could not be flagged for deletion: " . $this->repository->get_last_db_error() ) );
		}
		$this->cached_statuses = null;

		as_schedule_single_action(
			time(),
			'wpo_otd_reassign_orders',
			array( $status->status_key, $fallback_status, $id ),
			'wpo-otd'
		);

		return self::DELETION_SCHEDULED;
	}

	/**
	 * Whether a batch job for a status's deletion is pending or running, whatever key and fallback status it has.
	 *
	 * @param int $id
	 *
	 * @return bool
	 */
	private function has_deletion_job( int $id ): bool {
		$actions = as_get_scheduled_actions(
			array(
				'hook'     => 'wpo_otd_reassign_orders',
				'group'    => 'wpo-otd',
				'status'   => array( \ActionScheduler_Store::STATUS_PENDING, \ActionScheduler_Store::STATUS_RUNNING ),
				'per_page' => -1,
			)
		);

		foreach ( $actions as $action ) {
			$args = $action->get_args();

			if ( (int) ( $args[2] ?? 0 ) === $id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Process one batch of an in-progress status deletion.
	 *
	 * Reassigns up to a (filterable) batch of orders from the deleted status to
	 * the fallback, then either reschedules itself for the next batch or — once
	 * no orders remain — deletes the custom status row and fires the deleted
	 * action. Runs via the 'wpo_otd_reassign_orders' scheduled action.
	 *
	 * @param string $from_status The status key to reassign from (without 'wc-' prefix).
	 * @param string $to_status   The status key to reassign to (without 'wc-' prefix).
	 * @param int    $status_id   The ID of the custom status being deleted.
	 *
	 * @return void
	 */
	public function process_deletion_batch( string $from_status, string $to_status, int $status_id ): void {
		// Moving orders to the status they have would leave them there and run this job forever.
		if ( $from_status === $to_status ) {
			Logger::error( "The deletion of custom order status {$status_id} stopped: its orders cannot move from {$from_status} to the same status." );

			return;
		}

		/**
		 * Filter the number of orders reassigned per batch when draining a deleted status.
		 *
		 * @param int $limit Maximum orders processed per batch. Default 50.
		 */
		$limit  = apply_filters( 'wpo_otd_reassign_orders_batch_size', 50 );
		$orders = wc_get_orders(
			array(
				'status' => $from_status,
				'limit'  => $limit,
			)
		);

		foreach ( $orders as $order ) {
			try {
				/**
				 * Filter the fallback status assigned to an order during reassignment.
				 *
				 * @param string $to_status The fallback status key (without 'wc-' prefix).
				 * @param mixed  $order     The order being reassigned.
				 */
				$resolved_to_status = apply_filters( 'wpo_otd_reassign_orders_to_status', $to_status, $order );

				if ( is_callable( array( $order, 'update_status' ) ) ) {
					$order->update_status(
						$resolved_to_status,
						'Out the Door: ' . __( 'Status changed due to custom order status deletion.', 'out-the-door-order-tasks-workflows-for-woocommerce' )
					);
				}
			} catch ( \Throwable $e ) {
				Logger::warning( sprintf(
					'Failed to reassign order %d from status "%s" to "%s": %s',
					$order->get_id(),
					$from_status,
					$to_status,
					$e->getMessage()
				) );
			}
		}

		// Schedule next batch if there might be more orders.
		if ( count( $orders ) === $limit ) {
			as_schedule_single_action(
				time(),
				'wpo_otd_reassign_orders',
				array( $from_status, $to_status, $status_id ),
				'wpo-otd'
			);

			return;
		}

		// Double-check if any orders remain with the deleted status,
		// in case some were added since the last batch, to prevent race condition.
		$remaining_orders = wc_get_orders(
			array(
				'status' => $from_status,
				'limit'  => 1,
				'return' => 'ids',
			)
		);

		if ( 0 !== count( $remaining_orders ) ) {
			as_schedule_single_action(
				time(),
				'wpo_otd_reassign_orders',
				array( $from_status, $to_status, $status_id ),
				'wpo-otd'
			);

			return;
		}

		// Final batch done — now it's safe to remove the status.
		$this->remove( $status_id, $from_status, $to_status );
	}

	/**
	 * Delete the custom status row and fire the deleted action.
	 *
	 * Internal final step of the deletion flow: callers must ensure the status
	 * has already been drained of its orders (or never had any) before calling.
	 *
	 * @param int    $status_id   The ID of the custom status being deleted.
	 * @param string $from_status The status key that was deleted (without 'wc-' prefix).
	 * @param string $to_status   The fallback status key orders were reassigned to (without 'wc-' prefix).
	 *
	 * @return void
	 * @throws RuntimeException If the database does not delete the row.
	 */
	private function remove( int $status_id, string $from_status, string $to_status ): void {
		$deleted = $this->repository->where( 'id', $status_id )->delete();

		if ( false === $deleted ) {
			throw new RuntimeException( esc_html( "Custom order status {$status_id} could not be deleted: " . $this->repository->get_last_db_error() ) );
		}
		$this->cached_statuses = null;

		// No row was deleted: the status was gone already, and whatever deleted it announced it.
		if ( 0 === $deleted ) {
			return;
		}

		/**
		 * Action triggered after a custom order status is deleted.
		 *
		 * @param int    $status_id   The ID of the deleted custom order status.
		 * @param string $from_status The status key that was deleted (without 'wc-' prefix).
		 * @param string $to_status   The fallback status key that orders were reassigned to (without 'wc-' prefix).
		 */
		do_action( 'wpo_otd_custom_order_status_deleted', $status_id, $from_status, $to_status );
	}
}
