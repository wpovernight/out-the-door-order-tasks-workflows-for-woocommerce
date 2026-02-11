<?php

namespace WPO\AOM\Models;

defined( 'ABSPATH' ) || exit;

class Fulfillment extends BaseModel {
	public int $id;
	public int $quantity;

	/**
	 * Constructor
	 */
	public function __construct( array $data = array() ) {
		$this->id       = isset( $data['id'] ) && $data['id'] > 0 ? $data['id'] : 0;
		$this->quantity = absint( $data['quantity'] ?? 0 );
	}
}
