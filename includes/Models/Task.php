<?php

namespace WPO\AOM\Models;

use DateTime;
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
		$this->id          = absint( $data['id'] ?? 0 );
		$this->title       = $data['title'];
		$this->description = $data['description'] ?? '';
		$this->created_at  = new DateTime( $data['created_at'] ?? 'now' );
		$this->updated_at  = new DateTime( $data['updated_at'] ?? 'now' );
	}
}
