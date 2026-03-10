<?php

namespace WPO\AOM\Models;

use WPO\AOM\Core\Logger;
use WPO\AOM\Enums\TaskFieldTypes;

defined( 'ABSPATH' ) || exit;

class TaskField extends BaseModel {
	public int $id;
	public string $label;
	public string $type;
	public string $slug;
	public bool $is_required;
	public bool $is_editable;
	public bool $is_protected;

	protected array $guarded = array( 'id', 'type', 'slug' );

	/**
	 * Constructor.
	 *
	 * @param array $data
	 */
	public function __construct( array $data = array() ) {
		$this->id    = isset( $data['id'] ) && $data['id'] > 0 ? (int) $data['id'] : 0;
		$this->label = $data['label'];
		$this->slug  = $this->sanitize_slug( $data['slug'] ?? $this->label );

		$type = $data['type'] ?? '';
		if ( ! TaskFieldTypes::is_valid( $type ) ) {
			Logger::error( sprintf( 'Invalid field type "%s" for field "%s". Defaulting to text.', $type, $this->slug ) );
			$type = TaskFieldTypes::TEXT;
		}

		$this->type         = $type;
		$this->is_required  = isset( $data['is_required'] ) && $data['is_required'];
		$this->is_editable  = ! isset( $data['is_editable'] ) || $data['is_editable'];
		$this->is_protected = isset( $data['is_protected'] ) && $data['is_protected'];
	}

	/**
	 * Sanitize slug.
	 *
	 * @param string $slug
	 *
	 * @return string
	 */
	private function sanitize_slug( string $slug ): string {
		$sanitized = sanitize_title( $slug );

		return str_replace( '-', '_', $sanitized );
	}

	/**
	 * Get related field values.
	 *
	 * @return TaskFieldValue[]
	 */
	public function field_values(): array {
		return $this->has_many( TaskFieldValue::class, 'field_id' );
	}

	/**
	 * Get related field options.
	 *
	 * @return TaskFieldOption[]
	 */
	public function field_options(): array {
		return $this->has_many( TaskFieldOption::class, 'field_id' );
	}
}
