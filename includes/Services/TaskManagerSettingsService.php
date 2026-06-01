<?php

namespace WPO\AOM\Services;

defined( 'ABSPATH' ) || exit;

class TaskManagerSettingsService {
	public const OPTION_NAME = 'wpo_aom_task_manager_settings';

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
	 * Register hooks and filters.
	 *
	 * @return void
	 */
	public function register(): void {
		// Prime the cache on registration; the lazy load() below also covers
		// any caller that resolves the service before register() runs.
		$this->load();
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
		$settings = $this->load();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
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
		$this->load();

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
		$this->load();

		if ( array_key_exists( $key, $this->settings ) ) {
			unset( $this->settings[ $key ] );
			$this->has_unsaved_changes = true;
		}

		return $this;
	}

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		return $this->load();
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

		$result = update_option( self::OPTION_NAME, $this->settings );

		// update_option returns false when the value hasn't changed; treat that
		// as success because the persisted state already matches what we want.
		if ( $result || get_option( self::OPTION_NAME ) === $this->settings ) {
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
	private function load(): array {
		if ( null === $this->settings ) {
			$stored         = get_option( self::OPTION_NAME, array() );
			$this->settings = is_array( $stored ) ? $stored : array();
		}

		return $this->settings;
	}
}

