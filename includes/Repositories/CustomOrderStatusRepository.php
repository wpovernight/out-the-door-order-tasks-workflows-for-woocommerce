<?php

namespace WPO\AOM\Repositories;

use WPO\AOM\Models\CustomOrderStatus;

defined( 'ABSPATH' ) || exit;

class CustomOrderStatusRepository extends BaseRepository {

	private static string $table_name = 'custom_statuses';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( self::$table_name );
	}

	/**
	 * Get all custom order statuses.
	 *
	 * @return array
	 */
	public function all(): array {
		return array_map(
			fn( $row ) => new CustomOrderStatus( $row ),
			$this->get()
		);
	}

	/**
	 * Find a custom order status by ID.
	 *
	 * @param int $id
	 *
	 * @return CustomOrderStatus|null
	 */
	public function find_by_id( int $id ): ?CustomOrderStatus {
		$result = $this->find( $id );

		return $result ? new CustomOrderStatus( $result ) : null;
	}

	/**
	 * Find a custom order status by its key.
	 *
	 * @param string $status_key
	 *
	 * @return CustomOrderStatus|null
	 */
	public function find_by_key( string $status_key ): ?CustomOrderStatus {
		$result = $this->where( 'status_key', $status_key )->first();

		return $result ? new CustomOrderStatus( $result ) : null;
	}

	/**
	 * Insert a new custom order status.
	 *
	 * @param CustomOrderStatus $status
	 *
	 * @return int|false
	 */
	public function insert_status( CustomOrderStatus $status ) {
		return $this->insert( $status->to_db_array() );
	}

	/**
	 * Update an existing custom order status.
	 *
	 * @param CustomOrderStatus $status
	 *
	 * @return int
	 */
	public function update_status( CustomOrderStatus $status ): int {
		return $this->where( 'id', $status->id )->update( $status->to_db_array() );
	}

	/**
	 * Delete a custom order status.
	 *
	 * @param int $id
	 *
	 * @return int|false
	 */
	public function delete_status( int $id ): int {
		return $this->where( 'id', $id )->delete();
	}

}
