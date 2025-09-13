<?php

namespace WPO\AOM\Models;

use WPO\AOM\Enums\TaskFieldTypes;

defined( 'ABSPATH' ) || exit;

class TaskField extends BaseModel {

	public int $id;
	public string $label;
	public string $type;
	public bool $is_required;
	public bool $is_visible;
	public bool $is_protected;


	/**
	 * Constructor.
	 *
	 * @param array $data
	 */
	public function __construct( array $data = array() ) {
		$this->id    = absint( $data['id'] ?? 0 );
		$this->label = $data['label'];

		$type = $data['type'] ?? '';
		if ( ! TaskFieldTypes::is_valid( $type ) ) {
			throw new \InvalidArgumentException( "Invalid field type: $type" );
		}

		$this->type         = $type;
		$this->is_required  = isset( $data['is_required'] ) && $data['is_required'];
		$this->is_visible   = ! isset( $data['is_visible'] ) || $data['is_visible'];
		$this->is_protected = isset( $data['is_protected'] ) && $data['is_protected'];
	}

}
