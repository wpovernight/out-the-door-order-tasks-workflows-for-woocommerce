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

export function useColumnDeletion() {
	const { tasks, deleteTask, deleteFieldOption, moveTask } = useTasks();

	/**
	 * Delete every task currently using the given option, then delete the option.
	 *
	 * @param fieldId    The ID of the field the option belongs to.
	 * @param optionId   The option being deleted.
	 * @param onProgress Optional callback fired after each task is deleted.
	 */
	const deleteColumnAndTasks = useCallback(
		async (
			fieldId: number,
			optionId: number,
			onProgress?: ColumnDeletionProgress
		) => {
			const tasksInColumn = tasks.filter(
				(task) => task.status === optionId
			);
			const total = tasksInColumn.length;
			onProgress?.(0, total);
			let completed = 0;
			for (const task of tasksInColumn) {
				await deleteTask(task.id);
				completed += 1;
				onProgress?.(completed, total);
			}
			await deleteFieldOption(fieldId, optionId);
		},
		[tasks, deleteTask, deleteFieldOption]
	);

	/**
	 * Move every task currently using the given option to a different option,
	 * then delete the original option.
	 *
	 * @param fieldId    The ID of the field the option belongs to.
	 * @param optionId   The option being deleted.
	 * @param newStatus  The target option ID to move existing tasks into.
	 * @param onProgress Optional callback fired after each task is moved.
	 */
	const deleteColumnAndMoveTasks = useCallback(
		async (
			fieldId: number,
			optionId: number,
			newStatus: number,
			onProgress?: ColumnDeletionProgress
		) => {
			const tasksInColumn = tasks.filter(
				(task) => task.status === optionId
			);
			const total = tasksInColumn.length;
			onProgress?.(0, total);
			let completed = 0;
			for (const task of tasksInColumn) {
				await moveTask(task.id, null, newStatus);
				completed += 1;
				onProgress?.(completed, total);
			}
			await deleteFieldOption(fieldId, optionId);
		},
		[tasks, moveTask, deleteFieldOption]
	);

	return {
		deleteColumnAndTasks,
		deleteColumnAndMoveTasks,
	};
}