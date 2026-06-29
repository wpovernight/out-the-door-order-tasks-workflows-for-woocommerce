<?php

namespace WPO\AOM\Services;

defined( 'ABSPATH' ) || exit;

abstract class BaseSettingsService {
	protected const OPTION_NAME = '';

	/**
	 * Fallback values returned by get() when a key is absent from storage.
	 *
	 * Use for settings that always have a sensible default (e.g. `default_view`).
	 * Do NOT use for settings where "unset" is meaningful (e.g. a role assignment
	 * the user deliberately cleared) — there, leave the key out and let get()
	 * return null so the consumer can detect "no value set".
	 *
	 * @var array<string, mixed>
	 */
	protected array $defaults = array();

	/**
	 * In-memory cache of settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $settings = null;

	/**
	 * Whether the cache has diverged from the persisted value.
	 */
	private bool $has_unsaved_changes = false;

	/**
	 * Constructor.
	 *
	 * @throws \LogicException When the subclass has not defined OPTION_NAME.
	 */
	public function __construct() {
		if ( empty( static::OPTION_NAME ) ) {
			throw new \LogicException( static::class . ' must define OPTION_NAME.' );
		}
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key
	 * @param mixed  $default Returned when the key is absent.
	 *
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$settings = $this->fetchAll();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		// Per-call default wins over the subclass-registered default.
		return $default ?? ( $this->defaults[ $key ] ?? null );
	}

	/**
	 * Stage a setting value in the cache. Call save() to persist.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return self
	 */
	public function set( string $key, $value ): self {
		$this->fetchAll();

		if ( ! array_key_exists( $key, $this->settings ) || $this->settings[ $key ] !== $value ) {
			$this->settings[ $key ]    = $value;
			$this->has_unsaved_changes = true;
		}

		return $this;
	}

	/**
	 * Stage a setting removal in the cache. Call save() to persist.
	 *
	 * @param string $key
	 *
	 * @return self
	 */
	public function delete( string $key ): self {
		$this->fetchAll();

		if ( array_key_exists( $key, $this->settings ) ) {
			unset( $this->settings[ $key ] );
			$this->has_unsaved_changes = true;
		}

		return $this;
	}

	/**
	 * Persist any staged changes to the database.
	 *
	 * @return bool
	 */
	public function save(): bool {
		if ( ! $this->has_unsaved_changes ) {
			return true;
		}

		$result = update_option( static::OPTION_NAME, $this->settings );

		// update_option returns false when the value hasn't changed; treat that
		// as success because the persisted state already matches what we want.
		if ( $result || get_option( static::OPTION_NAME ) === $this->settings ) {
			$this->has_unsaved_changes = false;
			return true;
		}

		return false;
	}

	/**
	 * Whether the cache holds unsaved changes.
	 *
	 * @return bool
	 */
	public function has_unsaved_changes(): bool {
		return $this->has_unsaved_changes;
	}

	/**
	 * Load settings from the database into the cache, with a guard against
	 * non-array values that may have been stored by external code.
	 *
	 * @return array<string, mixed>
	 */
	private function fetchAll(): array {
		if ( null === $this->settings ) {
			$stored         = get_option( static::OPTION_NAME, array() );
			$this->settings = is_array( $stored ) ? $stored : array();
		}

		return $this->settings;
	}
}

