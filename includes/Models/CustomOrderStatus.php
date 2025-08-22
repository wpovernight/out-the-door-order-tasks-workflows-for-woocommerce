<?php

namespace WPO\AOM\Models;

defined( 'ABSPATH' ) || exit;

class CustomOrderStatus {

	public int $id;
	public string $status_key;
	public string $label;
	public string $background;

	/**
	 * Constructor.
	 *
	 * @param array $data
	 */
	public function __construct( array $data = array() ) {
		$this->id         = absint( $data['id'] ?? 0 );
		$this->status_key = $data['status_key'] ?? '';
		$this->label      = $data['label'] ?? '';
		$this->background = $data['background'] ?? '#ccc';
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
			'label'      => $this->label,
			'background' => $this->background,
		);
	}

	/**
	 * Get the prefixed status key for WooCommerce.
	 *
	 * @return string
	 */
	public function get_prefixed_status_key(): string {
		return 'wc-' . $this->status_key;
	}

}
