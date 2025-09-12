<?php

namespace WPO\AOM\Models;

use WPO\AOM\Repositories\BaseRepository;
use WPO\AOM\Repositories\RepositoryRegistry;

abstract class BaseModel {

	protected array $non_db_properties = array();

	/**
	 * Convert the model to an array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return get_object_vars( $this );
	}

	/**
	 * Convert the model to an array suitable for database storage.
	 *
	 * @return array<string, mixed>
	 */
	public function to_db_array(): array {
		$data = $this->to_array();

		// Exclude non-DB properties
		foreach ( $this->non_db_properties as $prop ) {
			unset( $data[ $prop ] );
		}

		return $data;
	}

	/**
	 * Get the repository for a given model class.
	 *
	 * @template T of BaseModel
	 * @param class-string<T> $model_class
	 *
	 * @return BaseRepository<T>
	 */
	protected function repository( string $model_class ): BaseRepository {
		return RepositoryRegistry::get( $model_class );
	}

	/**
	 * Fetch a related model by foreign key.
	 *
	 * @template T of BaseModel
	 * @param class-string<T> $related_class
	 * @param string $foreign_key
	 *
	 * @return T|null
	 */
	protected function related( string $related_class, string $foreign_key ): ?BaseModel {
		$id = $this->{$foreign_key} ?? null;
		if ( ! $id ) {
			return null;
		}

		return $this->repository( $related_class )->find( $id );
	}

}
