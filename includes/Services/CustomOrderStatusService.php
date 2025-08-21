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

	}

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
