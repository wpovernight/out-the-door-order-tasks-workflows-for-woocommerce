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
	 * Fetch a related model that this model owns.
	 *
	 * @template TModel of BaseModel
	 * @param string $related_class The related model class.
	 * @param string $foreign_key The foreign key on the related model.
	 * @param string $local_key The local key on this model.
	 *
	 * @return TModel|null
	 */
	protected function has_one( string $related_class, string $foreign_key, string $local_key = 'id' ): ?BaseModel {
		$local_id = $this->{$local_key} ?? null;
		if ( ! $local_id ) {
			return null;
		}

		return $this->repository( $related_class )->find_by( $foreign_key, $local_id );
	}

	/**
	 * Fetch a related model that owns this model.
	 *
	 * @template TModel of BaseModel
	 * @param string $related_class The related model class.
	 * @param string $foreign_key The foreign key on this model.
	 * @param string $owner_key The owner key on the related model.
	 *
	 * @return TModel|null
	 */
	protected function belongs_to_one( string $related_class, string $foreign_key, string $owner_key = 'id' ): ?BaseModel {
		$owner_id = $this->{$foreign_key} ?? null;
		if ( ! $owner_id ) {
			return null;
		}

		return $this->repository( $related_class )->find_by( $owner_key, $owner_id );
	}

	/**
	 * Fetch related models that this model owns.
	 *
	 * @template TModel of BaseModel
	 * @param string $related_class The related model class.
	 * @param string $foreign_key The foreign key on the related model.
	 * @param string $local_key The local key on this model.
	 *
	 * @return array<TModel>
	 */
	protected function has_many( string $related_class, string $foreign_key, string $local_key = 'id' ): array {
		$local_id = $this->{$local_key} ?? null;
		if ( ! $local_id ) {
			return array();
		}

		return $this->repository( $related_class )->find_all_by( $foreign_key, $local_id );
	}
}
