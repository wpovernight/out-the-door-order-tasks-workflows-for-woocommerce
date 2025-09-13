<?php

namespace WPO\AOM\Models;

use WPO\AOM\Repositories\BaseRepository;
use WPO\AOM\Repositories\RepositoryRegistry;

abstract class BaseModel {
	protected array $non_db_properties = array();

	/**
	 * Fill the model with data from an associative array.
	 *
	 * @param array<string, mixed> $data
	 */
	public function fill( array $data ): void {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->{$key} = $value;
			}
		}
	}

	/**
	 * Convert the model to an array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$data = get_object_vars( $this );
		unset( $data['non_db_properties'] );
		return $data;
	}

	/**
	 * Convert the model to an array suitable for database storage.
	 *
	 * @return array<string, mixed>
	 */
	public function to_db_array(): array {
		$data = $this->to_array();

		// Exclude non-DB properties
		foreach ( $this->non_db_properties as $property ) {
			unset( $data[ $property ] );
		}

		return $data;
	}

	/**
	 * Get the repository for a given model class.
	 *
	 * @template TModel of BaseModel
	 * @param class-string<TModel> $model_class
	 *
	 * @return BaseRepository<TModel>
	 */
	protected function repository( string $model_class ): BaseRepository {
		return RepositoryRegistry::get( $model_class );
	}

	/**
	 * Fetch a related model by foreign key.
	 *
	 * @template TModel of BaseModel
	 * @param class-string<TModel> $related_class
	 * @param string $foreign_key
	 *
	 * @return TModel|null
	 */
	protected function related( string $related_class, string $foreign_key ): ?BaseModel {
		$id = $this->{$foreign_key} ?? null;
		if ( ! $id ) {
			return null;
		}

		return $this->repository( $related_class )->find( $id );
	}
}
