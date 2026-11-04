<?php

namespace WPO\OTD\Repositories;

use WPO\OTD\Core\Logger;
use WPO\OTD\Models\BaseModel;
use WPO\OTD\Models\TaskField;

defined( 'ABSPATH' ) || exit;

class TaskFieldRepository extends BaseRepository {
	protected bool $enable_cache = true;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'task_fields', TaskField::class );
	}

	/**
	 * Map a row to a field, logging a type that fell back to text.
	 *
	 * @param array<string, mixed> $row
	 *
	 * @return BaseModel
	 */
	protected function map_to_model( array $row ): BaseModel {
		$field  = parent::map_to_model( $row );
		$notice = TaskField::type_fallback_notice( $row, $field->slug );

		if ( null !== $notice ) {
			Logger::error( $notice );
		}

		return $field;
	}
}
