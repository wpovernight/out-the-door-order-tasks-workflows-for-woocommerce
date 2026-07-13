<?php

namespace WPO\AOM\Services;

use InvalidArgumentException;
use WPO\AOM\Enums\DefaultTaskFields;
use WPO\AOM\Enums\TaskStatusRoleSettingKeys;
use WPO\AOM\Repositories\TaskFieldOptionRepository;

defined( 'ABSPATH' ) || exit;

class TaskStatusRoleService {
	protected readonly TaskManagerSettingsService $settings_service;
	protected readonly TaskFieldOptionRepository $task_field_option_repository;

	public function __construct(
		TaskManagerSettingsService $settings_service,
		TaskFieldOptionRepository $task_field_option_repository
	) {
		$this->settings_service             = $settings_service;
		$this->task_field_option_repository = $task_field_option_repository;
	}

	/**
	 * Get the option ID assigned to the "done" role.
	 *
	 * @return int|null Null when no option is currently assigned.
	 */
	public function get_done_field_option_id(): ?int {
		return $this->settings_service->get( TaskStatusRoleSettingKeys::DONE );
	}

	/**
	 * Assign an option to the "done" role. Pass null to clear the assignment.
	 *
	 * @param int|null $id
	 *
	 * @return void
	 * @throws InvalidArgumentException If the option does not exist or does not belong to the status field.
	 */
	public function set_done_field_option_id( ?int $id ): void {
		if ( null !== $id ) {
			$this->validate_status_option( $id );
		}

		$this->settings_service->set( TaskStatusRoleSettingKeys::DONE, $id )->save();
	}

	/**
	 * Get the option ID assigned to the "undone" role.
	 *
	 * @return int|null Null when no option is currently assigned.
	 */
	public function get_undone_field_option_id(): ?int {
		return $this->settings_service->get( TaskStatusRoleSettingKeys::UNDONE );
	}

	/**
	 * Assign an option to the "undone" role. Pass null to clear the assignment.
	 *
	 * @param int|null $id
	 *
	 * @return void
	 * @throws InvalidArgumentException If the option does not exist or does not belong to the status field.
	 */
	public function set_undone_field_option_id( ?int $id ): void {
		if ( null !== $id ) {
			$this->validate_status_option( $id );
		}

		$this->settings_service->set( TaskStatusRoleSettingKeys::UNDONE, $id )->save();
	}

	/**
	 * Check whether the given option is currently assigned to any role.
	 *
	 * Intended for guards like the delete-option flow that must refuse to remove
	 * an option still backing role-driven behavior.
	 *
	 * @param int $option_id
	 *
	 * @return bool
	 */
	public function is_field_option_assigned_to_any_role( int $option_id ): bool {
		foreach ( TaskStatusRoleSettingKeys::all() as $role ) {
			if ( $this->settings_service->get( $role ) === $option_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate that an option ID exists and belongs to the status field.
	 *
	 * @param int $option_id
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 */
	private function validate_status_option( int $option_id ): void {
		$option = $this->task_field_option_repository->find( $option_id );

		if ( null === $option ) {
			throw new InvalidArgumentException( esc_html( "No task field option found with ID $option_id." ) );
		}

		if ( $option->field_id !== DefaultTaskFields::STATUS ) {
			throw new InvalidArgumentException( esc_html( "Option $option_id does not belong to the status field." ) );
		}
	}

	/**
	 * Resolve the default "done" and "undone" status options by slug and assign
	 * them to the corresponding roles. Intended to be called once on first install.
	 *
	 * Idempotent by design: skips when either role is already assigned, so
	 * accidental re-invocation won't clobber existing assignments.
	 *
	 * @return void
	 */
	public function seed_default_role_assignments(): void {
		if (
			$this->get_done_field_option_id() !== null ||
			$this->get_undone_field_option_id() !== null
		) {
			return;
		}

		$done = $this->task_field_option_repository
			->where( 'field_id', DefaultTaskFields::STATUS )
			->where( 'slug', 'done' )
			->first();

		$undone = $this->task_field_option_repository
			->where( 'field_id', DefaultTaskFields::STATUS )
			->where( 'slug', 'in_progress' )
			->first();

		if ( $done ) {
			$this->set_done_field_option_id( $done->id );
		}
		if ( $undone ) {
			$this->set_undone_field_option_id( $undone->id );
		}
	}
}
