<?php

namespace WPO\OTD\Repositories;

use InvalidArgumentException;
use WPO\OTD\Enums\DefaultTaskFields;
use WPO\OTD\Models\Task;

defined( 'ABSPATH' ) || exit;

class TaskRepository extends BaseRepository {
	protected bool $timestamps = true;

	private readonly TaskFieldValueRepository $task_field_value_repository;

	/**
	 * Constructor.
	 *
	 * @param TaskFieldValueRepository $task_field_value_repository
	 */
	public function __construct( TaskFieldValueRepository $task_field_value_repository ) {
		$this->task_field_value_repository = $task_field_value_repository;

		parent::__construct( 'tasks', Task::class );
	}

	/**
	 * Get the position of the next task based on the given task ID.
	 *
	 * @param int|null   $given_task_id       The ID of the reference task.
	 * @param int        $target_status_id    The status ID to filter tasks.
	 * @param float|null $given_task_position The position of the reference task. (Optional if given_task_id is provided)
	 * @param int|null   $moving_task_id      The ID of the task being moved (to exclude from results)
	 *
	 * @return float|null
	 * @throws InvalidArgumentException
	 */
	public function get_next_task_position(
		?int $given_task_id,
		int $target_status_id,
		?float $given_task_position = null,
		?int $moving_task_id = null
	): ?float {
		if ( empty( $given_task_id ) && is_null( $given_task_position ) ) {
			throw new InvalidArgumentException( 'Either given_task_id or given_task_position must be provided.' );
		}

		if ( $given_task_id && empty( $given_task_position ) ) {
			$given_task_field_value = $this->task_field_value_repository
				->find_by_task_and_field( $given_task_id, DefaultTaskFields::POSITION );
			$given_task_position    = $given_task_field_value ? (float) $given_task_field_value->value : 0.0;
		}

		if ( ! is_finite( $given_task_position ) ) {
			$given_task_position = 0.0;
		}

		$query = $this->positions_in_status( $target_status_id );

		if ( ! empty( $given_task_id ) ) {
			$query->where( 'position.task_id', '!=', $given_task_id );
		}

		// Positions start at 1.0, so an empty one means none was given.
		if ( ! empty( $given_task_position ) ) {
			$query->where_raw(
				$this->wpdb->prepare(
					'CAST(position.value AS DECIMAL(10,5)) > %f',
					$given_task_position
				)
			);
		}

		if ( $moving_task_id ) {
			$query->where( 'position.task_id', '!=', $moving_task_id );
		}

		$next_task_value_field = $query
			->order_by_raw( 'CAST(position.value AS DECIMAL(10,5)) ASC' )
			->first();

		return $next_task_value_field ? (float) $next_task_value_field->value : null;
	}

	/**
	 * Get the position of the last task in a given status.
	 *
	 * @param int|null $given_task_id    The ID of the reference task to exclude.
	 * @param int      $target_status_id The status ID to filter tasks.
	 *
	 * @return float|null
	 */
	public function get_last_task_position(
		?int $given_task_id,
		int $target_status_id
	): ?float {
		$last_task_value_field = $this->positions_in_status( $target_status_id )
			->where( 'position.task_id', '!=', $given_task_id ?? 0 )
			->order_by_raw( 'CAST(position.value AS DECIMAL(10,5)) DESC' )
			->first();

		return $last_task_value_field ? (float) $last_task_value_field->value : null;
	}

	/**
	 * Start a query for the position values of the tasks in a status column, aliased "position".
	 *
	 * @param int $status_id
	 *
	 * @return TaskFieldValueRepository The value repository, holding the query.
	 */
	private function positions_in_status( int $status_id ): TaskFieldValueRepository {
		$table = $this->task_field_value_repository->get_table_full_name();

		return $this->task_field_value_repository
			->select( array( 'position.*' ) )
			->alias( 'position' )
			->join( "{$table} AS status", 'position.task_id', '=', 'status.task_id' )
			->where( 'status.field_id', DefaultTaskFields::STATUS )
			->where( 'status.value', $status_id )
			->where( 'position.field_id', DefaultTaskFields::POSITION );
	}

	/**
	 * Renumber the task positions of one status column 1, 2, 3, ... in their current order. Tasks at the same position
	 * keep the order of their IDs.
	 *
	 * @param int $status_id
	 *
	 * @return bool False when the database failed; nothing is renumbered then.
	 * @throws \Throwable
	 */
	public function rebalance_positions( int $status_id ): bool {
		return (bool) $this->transaction( function () use ( $status_id ) {
			// Locked until COMMIT, so no concurrent write changes a position while the column is renumbered.
			$rows = $this->positions_in_status( $status_id )
				->order_by_raw( 'CAST(position.value AS DECIMAL(10,5)) ASC, position.id ASC' )
				->lock_for_update()
				->get( true );

			// $wpdb gives an empty list for a failed query too, so only its error tells that apart from an empty column.
			if ( empty( $rows ) ) {
				return '' === $this->task_field_value_repository->get_last_db_error();
			}

			$table = $this->task_field_value_repository->get_table_full_name();
			$ids   = array_map( 'intval', array_column( $rows, 'id' ) );

			// CASE tries its branches in turn for every row, so a long column is written in chunks to stay fast.
			foreach ( array_chunk( $ids, 500 ) as $chunk_index => $chunk ) {
				$cases    = array();
				$bindings = array();

				foreach ( $chunk as $index => $id ) {
					$cases[]    = 'WHEN %d THEN %d';
					$bindings[] = $id;
					$bindings[] = $chunk_index * 500 + $index + 1;
				}

				$query = "UPDATE {$table} SET value = CASE id " . implode( ' ', $cases ) . ' END WHERE id IN (' . self::in_placeholders( $chunk, '%d' ) . ')';

				// No row changes when the positions were already 1, 2, 3, ...
				if ( false === $this->task_field_value_repository->execute_raw( $query, array_merge( $bindings, $chunk ) ) ) {
					return false;
				}
			}

			return true;
		} );
	}

	/**
	 * A text as a column of the tasks table holds it: the characters its charset cannot store removed, and cut to its
	 * length, which $wpdb counts in characters or bytes as the column's type does. In a utf8, utf8mb3 or utf8mb4 column, a cut
	 * never leaves part of a character.
	 *
	 * $wpdb refuses to write a text the column would change, so a text that does not fit fails every save.
	 *
	 * @param string $column
	 * @param string $text
	 *
	 * @return string The text as given when the column's charset cannot be read.
	 */
	public function fit_to_column( string $column, string $text ): string {
		$fitted = $this->wpdb->strip_invalid_text_for_column( $this->get_table_full_name(), $column, $text );

		return is_string( $fitted ) ? $fitted : $text;
	}

	/**
	 * Read the tasks with these IDs in one query, whatever is cached.
	 *
	 * Unlike find_many(), a failed query is told apart from finding none.
	 *
	 * @param int[] $ids
	 *
	 * @return array<int, Task>|null The tasks found, keyed by ID; one that does not exist is left out. Null when the
	 *                               database failed.
	 */
	public function find_by_ids( array $ids ): ?array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ), static fn( int $id ): bool => $id > 0 ) ) );

		if ( empty( $ids ) ) {
			return array();
		}

		$tasks = $this->where( 'id', 'IN', $ids )->get();

		if ( empty( $tasks ) && '' !== $this->get_last_db_error() ) {
			return null;
		}

		$found = array();

		foreach ( $tasks as $task ) {
			$found[ (int) $task->id ] = $task;
		}

		return $found;
	}
}
