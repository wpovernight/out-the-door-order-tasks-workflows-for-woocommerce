<?php

namespace WPO\OTD\Models;

use WPO\OTD\Enums\TaskFieldTypes;

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

		$this->type         = TaskFieldTypes::is_valid( $type ) ? $type : TaskFieldTypes::TEXT;
		$this->is_required  = isset( $data['is_required'] ) && $data['is_required'];
		$this->is_editable  = ! isset( $data['is_editable'] ) || $data['is_editable'];
		$this->is_protected = isset( $data['is_protected'] ) && $data['is_protected'];
	}

	/**
	 * What to log when the data names no valid type, so the field falls back to text.
	 *
	 * @param array  $data The data the field was built from.
	 * @param string $slug The field's slug.
	 *
	 * @return string|null Null when the type is valid.
	 */
	public static function type_fallback_notice( array $data, string $slug ): ?string {
		$type = $data['type'] ?? '';

		if ( TaskFieldTypes::is_valid( $type ) ) {
			return null;
		}

		return sprintf( 'Invalid field type "%s" for field "%s". Defaulting to text.', $type, $slug );
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
}
