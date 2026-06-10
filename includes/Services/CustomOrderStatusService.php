<?php

namespace WPO\AOM\Services;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use WPO\AOM\Models\CustomOrderStatus;
use WPO\AOM\Core\Logger;
use WPO\AOM\Repositories\CustomOrderStatusRepository;

defined( 'ABSPATH' ) || exit;

class CustomOrderStatusService {

	protected CustomOrderStatusRepository $repository;

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
	 * Register the service.
	 */
	public function register(): void {
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
		add_action( 'wpo_aom_reassign_orders', array( $this, 'process_deletion_batch' ), 10, 3 );
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
				__( 'Change status to %s', 'wpo-advanced-order-manager' ),
				esc_html( $status->label )
			);
		}

		// Insert new bulk actions after the default "Mark as completed/pending/etc." actions.
		$keys            = array_keys( $bulk_actions );
		$insert_position = count( $keys );
		foreach ( $keys as $index => $key ) {
			if ( strpos( $key, 'mark_' ) === 0 ) {
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
	 * Create a new custom order status.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return CustomOrderStatus
	 * @throws Exception
	 */
	public function create( array $data ): CustomOrderStatus {
		$status = new CustomOrderStatus( $data );

		// Ensure the status key is unique.
		$existing = $this->repository->where( 'status_key', $status->status_key )->first();
		if ( $existing ) {
			// Append a number to make it unique.
			$base_key = $status->status_key;
			$counter  = 2;
			do {
				$status->status_key = $base_key . '-' . $counter;
				$existing           = $this->repository->where( 'status_key', $status->status_key )->first();
				$counter++;
			} while ( $existing );
		}

		$result = $this->repository->save( $status );

		if ( ! $result ) {
			throw new Exception( esc_html__( 'Failed to create custom order status', 'wpo-advanced-order-manager' ) );
		}

		$status->id            = $result;
		$this->cached_statuses = null;

		/**
		 * Action triggered after a custom order status is created.
		 *
		 * @param CustomOrderStatus $status The newly created custom order status.
		 */
		do_action( 'wpo_aom_custom_order_status_created', $status );

		return $status;
	}

	/**
	 * Update a custom order status by ID.
	 *
	 * @param int $id
	 * @param array<string, mixed> $data
	 *
	 * @return CustomOrderStatus
	 * @throws RuntimeException|InvalidArgumentException
	 */
	public function update( int $id, array $data ): CustomOrderStatus {
		$custom_status = $this->repository->find( $id );
		if ( ! $custom_status ) {
			throw new InvalidArgumentException( esc_html__( 'Custom order status not found', 'wpo-advanced-order-manager' ) );
		}

		$custom_status->fill( $data );
		$result = $this->repository->save( $custom_status );

		if ( false === $result ) {
			throw new RuntimeException( esc_html__( 'Failed to update custom order status', 'wpo-advanced-order-manager' ) );
		}

		$this->cached_statuses = null;

		/**
		 * Action triggered after a custom order status is updated.
		 *
		 * @param CustomOrderStatus $custom_status  The updated custom order status.
		 * @param array             $data           The data used for the update.
		 */
		do_action( 'wpo_aom_custom_order_status_updated', $custom_status, $data );

		return $custom_status;
	}

	/**
	 * Request deletion of a custom order status.
	 *
	 * The status is not removed synchronously. Its orders must first be drained
	 * to a fallback status, so this only schedules the async batch job and
	 * returns. The row is deleted once the final batch completes (see
	 * process_deletion_batch()); until then the status stays registered so
	 * wc_get_orders() can keep matching its orders.
	 *
	 * @param int    $id              The custom status ID to delete.
	 * @param string $fallback_status The status to assign to affected orders (default: 'on-hold').
	 *
	 * @return bool True if deletion was scheduled, false if the status was not found.
	 */
	public function request_deletion( int $id, string $fallback_status = 'on-hold' ): bool {
		$status = $this->find( $id );
		if ( ! $status ) {
			return false;
		}

		if ( $status->is_deleting ) {
			return true; // Deletion already in progress.
		}

		// Mark the status as pending deletion.
		$status->is_deleting = true;
		$this->repository->save( $status );

		$this->cached_statuses = null;

		// Schedule the drain. Do NOT delete the row yet — it must stay
		// registered so wc_get_orders() can still match its orders
		as_schedule_single_action(
			time(),
			'wpo_aom_reassign_orders',
			array( $status->status_key, $fallback_status, $id ),
			'wpo-aom'
		);

		return true;
	}

	/**
	 * Process one batch of an in-progress status deletion.
	 *
	 * Reassigns up to a (filterable) batch of orders from the deleted status to
	 * the fallback, then either reschedules itself for the next batch or — once
	 * no orders remain — deletes the custom status row and fires the deleted
	 * action. Runs via the 'wpo_aom_reassign_orders' scheduled action.
	 *
	 * @param string $from_status The status key to reassign from (without 'wc-' prefix).
	 * @param string $to_status   The status key to reassign to (without 'wc-' prefix).
	 * @param int    $status_id   The ID of the custom status being deleted.
	 *
	 * @return void
	 */
	public function process_deletion_batch( string $from_status, string $to_status, int $status_id ): void {
		$limit  = apply_filters( 'wpo_aom_reassign_orders_batch_size', 50 );
		$orders = wc_get_orders(
			array(
				'status' => $from_status,
				'limit'  => $limit,
			)
		);

		foreach ( $orders as $order ) {
			try {
				$resolved_to_status = apply_filters( 'wpo_aom_reassign_orders_to_status', $to_status, $order );

				if ( is_callable( array( $order, 'update_status' ) ) ) {
					$order->update_status(
						$resolved_to_status,
						'WPO AOM: ' . __( 'Status changed due to custom order status deletion.', 'wpo-advanced-order-manager' )
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
				'wpo_aom_reassign_orders',
				array( $from_status, $to_status, $status_id ),
				'wpo-aom'
			);

			return;
		}

		// Final batch done — now it's safe to remove the status.
		$this->repository->where( 'id', $status_id )->delete();
		$this->cached_statuses = null;

		do_action( 'wpo_aom_custom_order_status_deleted', $status_id, $from_status, $to_status );
	}
}
