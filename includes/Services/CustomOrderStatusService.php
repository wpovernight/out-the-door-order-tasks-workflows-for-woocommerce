<?php

namespace WPO\AOM\Services;

use WPO\AOM\Models\CustomOrderStatus;
use WPO\AOM\Repositories\CustomOrderStatusRepository;

defined( 'ABSPATH' ) || exit;

class CustomOrderStatusService {

	protected CustomOrderStatusRepository $repository;

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
		add_filter( 'wc_order_statuses', array( $this, 'add_to_order_statuses' ) );
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( $this, 'register_order_statuses' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_to_bulk_action' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_to_bulk_action' ) ); // HPOS support
		add_action( 'admin_enqueue_scripts', array( $this, 'add_dynamic_style' ), 99 );
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
			$order_statuses[ $status->get_prefixed_status_key() ] = esc_html__( $status->label, 'wpo-aom' );
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
			$label = esc_html__( $status->label, 'wpo-aom' );

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
			$bulk_actions[ 'mark_' . $status->get_prefixed_status_key() ] = sprintf( __( 'Change status to %s', 'wpo-aom' ), esc_html__( $status->label, 'wpo-aom' ) );
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
		return $this->repository->all();
	}

	/**
	 * Get a custom status by ID.
	 *
	 * @param int $id
	 *
	 * @return CustomOrderStatus|null
	 */
	public function find( int $id ): ?CustomOrderStatus {
		return $this->repository->find_by_id( $id );
	}

	/**
	 * Get a custom status by its key.
	 *
	 * @param string $status_key
	 *
	 * @return CustomOrderStatus|null
	 */
	public function find_by_key( string $status_key ): ?CustomOrderStatus {
		return $this->repository->find_by_key( $status_key );
	}

	/**
	 * Create a new custom order status.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return int|false Inserted ID or false on failure
	 */
	public function create( array $data ) {
		$status = new CustomOrderStatus( $data );

		return $this->repository->insert_status( $status );
	}

	/**
	 * Update a custom order status by ID.
	 *
	 * @param int $id
	 * @param array<string, mixed> $data
	 *
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		$existing = $this->repository->find_by_id( $id );
		if ( ! $existing ) {
			return false;
		}

		$model     = new CustomOrderStatus( array_merge( $existing->to_array(), $data ) );
		$model->id = $id;

		return $this->repository->update_status( $model );
	}

	/**
	 * Delete a custom order status by ID.
	 *
	 * @param int $id
	 *
	 * @return bool
	 */
	public function delete( int $id ): bool {
		return $this->repository->delete_status( $id );
	}

}
