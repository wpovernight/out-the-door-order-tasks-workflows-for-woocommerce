<?php

namespace WPO\AOM\Contracts;

defined( 'ABSPATH' ) || exit;

interface ArraySerializableModel {

	/**
	 * Convert the model to an array.
	 *
	 * @return array
	 */
	public function to_array(): array;

	/**
	 * Convert the model to an array suitable for database storage.
	 *
	 * @return array
	 */
	public function to_db_array(): array;
}
