<?php

namespace WPO\AOM\Models;

use DateTimeImmutable;
use Exception;

defined( 'ABSPATH' ) || exit;

class Task extends BaseModel {

	public int $id;
	public string $title;
	public string $description;
	public DateTimeImmutable $created_at;
	public DateTimeImmutable $updated_at;


	/**
	 * Constructor.
	 *
	 * @throws Exception
	 */
	public function __construct( array $data = array() ) {
		$this->id          = absint( $data['id'] ?? 0 );
		$this->title       = $data['title'];
		$this->description = $data['description'] ?? '';
		$this->created_at  = new DateTimeImmutable( $data['created_at'] ?? 'now' );
		$this->updated_at  = new DateTimeImmutable( $data['updated_at'] ?? 'now' );
	}

}
