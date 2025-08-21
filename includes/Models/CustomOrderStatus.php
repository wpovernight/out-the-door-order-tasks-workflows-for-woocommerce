<?php

namespace WPO\AOM\Models;

defined( 'ABSPATH' ) || exit;

class CustomOrderStatus {

	public int $id;
	public string $status_key;
	public string $name;
	public string $color;

	/**
	 * Constructor.
	 *
	 * @param array $data
	 */
	public function __construct( array $data = array() ) {
		$this->id         = absint( $data['id'] ?? 0 );
		$this->status_key = $data['status_key'] ?? '';
		$this->name       = $data['name'] ?? '';
		$this->color      = $data['color'] ?? '#ccc';
	}

	/**
	 * Convert the model to an array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'id'         => $this->id,
			'status_key' => $this->status_key,
			'name'       => $this->name,
			'color'      => $this->color,
		);
	}

}
