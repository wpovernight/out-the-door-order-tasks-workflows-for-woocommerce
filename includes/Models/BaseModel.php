<?php

namespace WPO\OTD\Models;

defined( 'ABSPATH' ) || exit;

abstract class BaseModel {
	protected array $non_db_properties = array();
	protected array $guarded           = array( 'id' );

	/**
	 * Fill the model with data from an associative array.
	 * Only public properties are filled, and guarded ones (like 'id') never are.
	 *
	 * @param array<string, mixed> $data
	 */
	public function fill( array $data ): void {
		foreach ( $data as $key => $value ) {
			if ( $this->is_fillable( (string) $key ) ) {
				$this->{$key} = $value;
			}
		}
	}

	/**
	 * Whether fill() may set a property.
	 *
	 * Non-public properties are the model's own bookkeeping ($guarded, $non_db_properties): letting
	 * data reach them would let it lift the guard on 'id'.
	 *
	 * @param string $property
	 *
	 * @return bool
	 */
	private function is_fillable( string $property ): bool {
		return property_exists( $this, $property )
			&& ( new \ReflectionProperty( $this, $property ) )->isPublic()
			&& ! in_array( $property, $this->guarded, true );
	}

	/**
	 * Convert the model to an array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$data = get_object_vars( $this );

		// Convert any DateTime properties to strings.
		foreach ( $data as $key => $value ) {
			if ( $value instanceof \DateTimeInterface ) {
				$data[ $key ] = $value->format( 'c' ); // ISO 8601 format
			}
		}

		unset( $data['non_db_properties'] );
		unset( $data['guarded'] );

		return $data;
	}

	/**
	 * Convert the model to an array suitable for database storage.
	 *
	 * @return array<string, mixed>
	 */
	public function to_db_array(): array {
		$data = get_object_vars( $this );

		// Exclude non-DB properties
		foreach ( $this->non_db_properties as $property ) {
			unset( $data[ $property ] );
		}

		// Exclude class properties that are not meant to be stored in the database.
		unset( $data['non_db_properties'] );
		unset( $data['guarded'] );

		// Never include ID in INSERT (auto-generated) or UPDATE (used in WHERE, not SET)
		if ( isset( $data['id'] ) ) {
			unset( $data['id'] );
		}

		// Never update created_at (set once on creation)
		if ( isset( $data['created_at'] ) && property_exists( $this, 'id' ) && isset( $this->id ) && $this->id > 0 ) {
			unset( $data['created_at'] );
		}

		// The repository stamps updated_at in UTC on every write
		if ( isset( $data['updated_at'] ) ) {
			unset( $data['updated_at'] );
		}

		// Convert any remaining DateTime properties to MySQL DATETIME format, in UTC as the columns hold it.
		foreach ( $data as $key => $value ) {
			if ( $value instanceof \DateTimeInterface ) {
				$data[ $key ] = \DateTimeImmutable::createFromInterface( $value )
					->setTimezone( new \DateTimeZone( 'UTC' ) )
					->format( 'Y-m-d H:i:s' );
			}
		}

		return $data;
	}

}
