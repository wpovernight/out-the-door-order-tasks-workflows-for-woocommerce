<?php

namespace WPO\AOM\Models;

defined( 'ABSPATH' ) || exit;

class TaskFieldOption extends BaseModel {
	public int $id;
	public int $field_id;
	public string $slug;
	public string $label;
	public string $color;
	public int $position;


	/**
	 * Constructor.
	 *
	 * @param array $data
	 */
	public function __construct( array $data = array() ) {
		$this->id       = absint( $data['id'] ?? 0 );
		$this->field_id = absint( $data['field_id'] );
		$this->slug     = $data['slug'];
		$this->label    = $data['label'];
		$this->color    = $data['color'] ?? '#000000';
		$this->position = absint( $data['position'] ?? 0 );
	}

	/**
	 * Get the associated TaskField.
	 *
	 * @return TaskField|null
	 */
	public function field(): ?TaskField {
		return $this->belongs_to_one( TaskField::class, 'field_id' );
	}
}
