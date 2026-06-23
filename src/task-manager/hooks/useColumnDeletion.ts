import { useCallback } from 'react';
import { useTasks } from '@shared/context/TaskContext';

/**
 * Orchestration helpers for the column-deletion flow. The backend blocks the
 * raw delete-option endpoint when any task references the option, so the UI
 * must drain the column first — either by deleting its tasks or moving them
 * elsewhere — before the option itself can be removed.
 *
 * Each method bails on the first error so the user sees a clear failure point
 * rather than partial-success state. The column is only deleted after all
 * task-side work has succeeded.
 */
/**
 * Reports per-task progress while a column is being drained.
 *
 * @param completed Number of tasks processed so far.
 * @param total     Total number of tasks to process (0 for an empty column).
 */
export type ColumnDeletionProgress = ( completed: number, total: number ) => void;

/**
 * Polled between tasks to allow the caller to abort a long-running drain.
 * The in-flight request can't be un-sent, so cancellation takes effect after
 * the current task settles and before the next one starts.
 *
 * @return `true` to stop draining before the next task.
 */
export type ColumnDeletionCancel = () => boolean;

/**
 * Outcome of a drain operation. `canceled` means the caller aborted partway
 * through, so the option was intentionally left in place.
 */
export type ColumnDeletionResult = 'completed' | 'canceled';

export function useColumnDeletion() {
	const { tasks, deleteTask, deleteFieldOption, moveTask } = useTasks();

	/**
	 * Delete every task currently using the given option, then delete the option.
	 *
	 * @param fieldId      The ID of the field the option belongs to.
	 * @param optionId     The option being deleted.
	 * @param onProgress   Optional callback fired as each task's optimistic delete is applied.
	 * @param shouldCancel Optional predicate polled before each task.
	 */
	const deleteColumnAndTasks = useCallback(
		async (
			fieldId: number,
			optionId: number,
			onProgress?: ColumnDeletionProgress,
			shouldCancel?: ColumnDeletionCancel
		): Promise<ColumnDeletionResult> => {
			const tasksInColumn = tasks.filter(
				(task) => task.status === optionId
			);
			const total = tasksInColumn.length;
			onProgress?.(0, total);
			let completed = 0;
			for (const task of tasksInColumn) {
				if (shouldCancel?.()) {
					return 'canceled';
				}
				// deleteTask removes the card optimistically (before its own
				// server await), so advance the bar as soon as that synchronous
				// update is kicked off — otherwise progress lags a full server
				// round-trip behind the card the user already saw disappear.
				const pending = deleteTask(task.id);
				completed += 1;
				onProgress?.(completed, total);
				await pending;
			}
			// Don't delete the option if the user canceled on the final tick.
			if (shouldCancel?.()) {
				return 'canceled';
			}
			await deleteFieldOption(fieldId, optionId);
			return 'completed';
		},
		[tasks, deleteTask, deleteFieldOption]
	);

	/**
	 * Move every task currently using the given option to a different option,
	 * then delete the original option.
	 *
	 * @param fieldId      The ID of the field the option belongs to.
	 * @param optionId     The option being deleted.
	 * @param newStatus    The target option ID to move existing tasks into.
	 * @param onProgress   Optional callback fired as each task's optimistic move is applied.
	 * @param shouldCancel Optional predicate polled before each task.
	 */
	const deleteColumnAndMoveTasks = useCallback(
		async (
			fieldId: number,
			optionId: number,
			newStatus: number,
			onProgress?: ColumnDeletionProgress,
			shouldCancel?: ColumnDeletionCancel
		): Promise<ColumnDeletionResult> => {
			const tasksInColumn = tasks.filter(
				(task) => task.status === optionId
			);
			const total = tasksInColumn.length;
			onProgress?.(0, total);
			let completed = 0;
			for (const task of tasksInColumn) {
				if (shouldCancel?.()) {
					return 'canceled';
				}
				// moveTask relocates the card optimistically (before its own
				// server await), so advance the bar as soon as that synchronous
				// update is kicked off — otherwise progress lags a full server
				// round-trip behind the card the user already saw move.
				const pending = moveTask(task.id, null, newStatus);
				completed += 1;
				onProgress?.(completed, total);
				await pending;
			}
			// Don't delete the option if the user canceled on the final tick.
			if (shouldCancel?.()) {
				return 'canceled';
			}
			await deleteFieldOption(fieldId, optionId);
			return 'completed';
		},
		[tasks, moveTask, deleteFieldOption]
	);

	return {
		deleteColumnAndTasks,
		deleteColumnAndMoveTasks,
	};
}