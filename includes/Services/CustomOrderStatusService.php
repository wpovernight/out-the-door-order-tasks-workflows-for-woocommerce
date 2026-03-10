<?php

namespace WPO\AOM\Services;

use Exception;
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
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( $this, 'register_order_statuses' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_to_bulk_action' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_to_bulk_action' ) ); // HPOS support

		// Add dynamic styles for custom order statuses in the orders page.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_dynamic_style' ), 99 );

		// Reassign orders when a custom status is deleted.
		add_action( 'wpo_aom_reassign_orders', array( $this, 'reassign_orders' ), 10, 2 );
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
				'label_count'               => _n_noop(
					$label . ' <span class="count">(%s)</span>',
					$label . ' <span class="count">(%s)</span>',
					'wpo-aom'
				)
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
		// We can introduce a new setting to toggle this feature if needed.
		$statuses = $this->all();

		foreach ( $statuses as $status ) {
			$bulk_actions[ 'mark_' . $status->get_prefixed_status_key() ] = sprintf(
				/* translators: %s: status label */
				__( 'Change status to %s', 'wpo-aom' ),
				esc_html( $status->label )
			);
		}

		return $bulk_actions;
	}

	/**
	 * Add dynamic styles for custom order statuses colors.
	 *
	 * @return void
	 */
	public function add_dynamic_style() {
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
	 * @return CustomOrderStatus Inserted ID or false on failure
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
			throw new Exception( __( 'Failed to create custom order status', 'wpo-aom' ) );
		}

		$status->id            = $result;
		$this->cached_statuses = null;

		return $status;
	}

	/**
	 * Update a custom order status by ID.
	 *
	 * @param int $id
	 * @param array<string, mixed> $data
	 *
	 * @return CustomOrderStatus
	 * @throws Exception
	 */
	public function update( int $id, array $data ): CustomOrderStatus {
		$custom_status = $this->repository->find( $id );
		if ( ! $custom_status ) {
			throw new Exception( __( 'Custom order status not found', 'wpo-aom' ) );
		}

		$custom_status->fill( $data );
		$result = $this->repository->save( $custom_status );

		if ( false === $result ) {
			throw new RuntimeException( __( 'Failed to update custom order status', 'wpo-aom' ) );
		}

		$this->cached_statuses = null;

		return $custom_status;
	}

	/**
	 * Delete a custom order status by ID.
	 * Updates all orders with the deleted status to a fallback status before deletion.
	 *
	 * @param int    $id
	 * @param string $fallback_status The status to assign to affected orders (default: 'on-hold').
	 *
	 * @return bool
	 */
	public function delete( int $id, string $fallback_status = 'on-hold' ): bool {
		$status = $this->find( $id );
		if ( ! $status ) {
			return false;
		}

		// Update all orders with this custom status to the fallback status.
		as_schedule_single_action(
			time(),
			'wpo_aom_reassign_orders',
			array( $status->status_key, $fallback_status ),
			'wpo-aom'
		);

		$result                = $this->repository->where( 'id', $id )->delete();
		$this->cached_statuses = null;

		return $result;
	}

	/**
	 * Reassign orders from one status to another.
	 *
	 * @param string $from_status The status key to reassign from (without 'wc-' prefix).
	 * @param string $to_status   The status key to reassign to (without 'wc-' prefix).
	 *
	 * @return void
	 */
	public function reassign_orders( string $from_status, string $to_status ): void {
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

				$order->update_status(
					$resolved_to_status,
					'WPO AOM: ' . __( 'Status changed due to custom order status deletion.', 'wpo-aom' )
				);
			} catch ( \Exception $e ) {
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
				array( $from_status, $to_status ),
				'wpo-aom'
			);
		}
	}
}
