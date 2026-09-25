<?php

namespace WPO\OTD\Models;

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
		$this->id       = isset( $data['id'] ) && $data['id'] > 0 ? (int) $data['id'] : 0;
		$this->task_id  = absint( $data['task_id'] );
		$this->field_id = absint( $data['field_id'] );
		$this->value    = $data['value'] ?? null;
	}

	/**
	 * Get the associated Task.
	 *
	 * @return Task|null
	 */
	public function task(): ?Task {
		return $this->belongs_to_one( Task::class, 'task_id' );
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
