<?php

namespace WPO\AOM\Models;

defined( 'ABSPATH' ) || exit;

class TaskFieldOptions extends BaseModel {

	public int $id;
	public int $field_id;
	public string $label;
	public string $color;


	/**
	 * Constructor.
	 *
	 * @param array $data
	 */
	public function __construct( array $data = array() ) {
		$this->id       = absint( $data['id'] ?? 0 );
		$this->field_id = absint( $data['field_id'] );
		$this->label    = $data['label'] ?? '';
		$this->color    = $data['color'] ?? '#000000';
	}

}
