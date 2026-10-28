<?php

namespace WPO\OTD\Models;

use DateTime;
use DateTimeZone;
use Exception;

defined( 'ABSPATH' ) || exit;

class Task extends BaseModel {
	public int $id;
	public string $title;
	public string $description;
	public DateTime $created_at;
	public DateTime $updated_at;


	/**
	 * Constructor.
	 *
	 * @throws Exception
	 */
	public function __construct( array $data = array() ) {
		$this->id          = isset( $data['id'] ) && $data['id'] > 0 ? (int) $data['id'] : 0;
		$this->title       = $data['title'];
		$this->description = $data['description'] ?? '';
		// The columns hold UTC, whatever PHP's default time zone is.
		$this->created_at = new DateTime( $data['created_at'] ?? 'now', new DateTimeZone( 'UTC' ) );
		$this->updated_at = new DateTime( $data['updated_at'] ?? 'now', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Get related field values.
	 *
	 * @return TaskFieldValue[]
	 */
	public function field_values(): array {
		return $this->has_many( TaskFieldValue::class, 'task_id' );
	}
}
