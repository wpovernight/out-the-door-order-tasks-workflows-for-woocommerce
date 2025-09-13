<?php

namespace WPO\AOM\Models;

defined( 'ABSPATH' ) || exit;

class TaskFieldValue extends BaseModel {
	public int $id;
	public int $task_id;
	public int $field_id;
	public ?string $value;


	/**
	 * Constructor.
	 *
	 * @param array $data
	 */
	public function __construct( array $data = array() ) {
		$this->id       = absint( $data['id'] ?? 0 );
		$this->task_id  = absint( $data['task_id'] );
		$this->field_id = absint( $data['field_id'] );
		$this->value    = $data['value'] ?? null;
	}
}
