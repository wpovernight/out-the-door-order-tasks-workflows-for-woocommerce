import React, { useContext, useEffect, useState, useCallback } from 'react';
import {
	Task,
	TASK_FINISH_STATUS_SLUG,
	TASK_UNFINISHED_STATUS_SLUG,
} from '@shared/types/task';
import { useTasks } from '@shared/context/TaskContext';
import { groupAndSortTasks } from '../../../utils/task-sort';

interface ViewTaskContextType {
	viewTasks: Record<string, Task[]>;
	setViewTasks: React.Dispatch<React.SetStateAction<Record<string, Task[]>>>;
	selectedTask: Task | null;
	selectTask: (task: Task) => void;
	clearSelectedTask: () => void;
	finishTask: (taskId: number) => Promise<boolean>;
	unfinishTask: (taskId: number) => Promise<boolean>;
}

const ViewTaskContext = React.createContext<ViewTaskContextType | undefined>(
	undefined
);

export const ViewTaskProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const {
		tasks,
		fieldOptions,
		finishTask: globalFinishTask,
		unfinishTask: globalUnfinishTask,
	} = useTasks();
	const [viewTasks, setViewTasks] = useState<Record<string, Task[]>>({});
	const [selectedTask, setSelectedTask] = useState<Task | null>(null);

	const selectTask = useCallback((task: Task) => {
		setSelectedTask(task);
	}, []);

	const clearSelectedTask = useCallback(() => {
		setSelectedTask(null);
	}, []);

	// Wrapper function that updates both global state and local viewTasks.
	const finishTask = useCallback(
		async (taskId: number): Promise<boolean> => {
			let previousState: Record<string, Task[]> | null = null;
			let taskToMove: Task | null = null;

			// Update UI immediately before API call.
			setViewTasks((prev) => {
				previousState = structuredClone(prev);
				const updated = structuredClone(prev);

				// Find and remove the task from its current column.
				for (const status in updated) {
					const taskIndex = updated[status].findIndex(
						(t) => t.id === taskId
					);
					if (taskIndex !== -1) {
						[taskToMove] = updated[status].splice(taskIndex, 1);
						break;
					}
				}

				// Add task to the completed column if found.
				if (taskToMove && updated[TASK_FINISH_STATUS_SLUG]) {
					const completedTask: Task = {
						...taskToMove,
						status: TASK_FINISH_STATUS_SLUG,
					};

					updated[TASK_FINISH_STATUS_SLUG].push(completedTask);
				}

				return updated;
			});

			try {
				const success = await globalFinishTask(taskId);

				if (!success) {
					// Rollback if API call failed.
					if (previousState) {
						setViewTasks(previousState);
					}
				}

				return success;
			} catch (error) {
				console.error('Failed to finish task:', error);

				// Rollback on error.
				if (previousState) {
					setViewTasks(previousState);
				}

				throw error;
			}
		},
		[globalFinishTask]
	);

	// Wrapper function for unfinishing tasks
	const unfinishTask = useCallback(
		async (taskId: number): Promise<boolean> => {
			let previousState: Record<string, Task[]> | null = null;
			let taskToMove: Task | null = null;

			// Update UI immediately before API call.
			setViewTasks((prev) => {
				previousState = structuredClone(prev);
				const updated = structuredClone(prev);

				// Find and remove the task from FINISHED column.
				if (updated[TASK_FINISH_STATUS_SLUG]) {
					const taskIndex = updated[
						TASK_FINISH_STATUS_SLUG
					].findIndex((t) => t.id === taskId);
					if (taskIndex !== -1) {
						[taskToMove] = updated[TASK_FINISH_STATUS_SLUG].splice(
							taskIndex,
							1
						);
					}
				}

				// Add task to the UNFINISHED column if found
				if (taskToMove && updated[TASK_UNFINISHED_STATUS_SLUG]) {
					const unfinishedTask: Task = {
						...taskToMove,
						status: TASK_UNFINISHED_STATUS_SLUG,
					};

					updated[TASK_UNFINISHED_STATUS_SLUG].push(unfinishedTask);
				}

				return updated;
			});

			try {
				const success = await globalUnfinishTask(taskId);

				if (!success) {
					// Rollback if API call failed
					if (previousState) {
						setViewTasks(previousState);
					}
				}

				return success;
			} catch (error) {
				console.error('Failed to unfinish task:', error);

				// Rollback on error
				if (previousState) {
					setViewTasks(previousState);
				}

				throw error;
			}
		},
		[globalUnfinishTask]
	);

	useEffect(() => {
		const statuses = fieldOptions.status || [];
		if (
			!fieldOptions.status ||
			tasks.length === 0 ||
			statuses.length === 0
		) {
			return;
		}

		const grouped = groupAndSortTasks(tasks, statuses);

		// Only update if the grouped tasks are actually different
		// This prevents unnecessary rerenders when global tasks update
		// with the same data that's already in local viewTasks
		setViewTasks((prev) => {
			// If prev is empty, always update
			if (Object.keys(prev).length === 0) {
				return grouped;
			}

			// If the number of columns has been changed, update.
			if (Object.keys(prev).length !== Object.keys(grouped).length) {
				return grouped;
			}

			// Check if any column has changed
			let hasChanges = false;
			for (const column in grouped) {
				const prevColumn = prev[column] || [];
				const newColumn = grouped[column] || [];

				// Check if lengths differ
				if (prevColumn.length !== newColumn.length) {
					hasChanges = true;
					break;
				}

				// Check if task IDs or positions differ
				for (let i = 0; i < newColumn.length; i++) {
					if (
						prevColumn[i]?.id !== newColumn[i]?.id ||
						prevColumn[i]?.position !== newColumn[i]?.position
					) {
						hasChanges = true;
						break;
					}
				}

				if (hasChanges) {
					break;
				}
			}

			return hasChanges ? grouped : prev;
		});
	}, [tasks, fieldOptions]);

	return (
		<ViewTaskContext.Provider
			value={{
				viewTasks,
				setViewTasks,
				selectedTask,
				selectTask,
				clearSelectedTask,
				finishTask,
				unfinishTask,
			}}
		>
			{children}
		</ViewTaskContext.Provider>
	);
};

export const useViewTasks = (): ViewTaskContextType => {
	const context = useContext(ViewTaskContext);
	if (!context) {
		throw new Error('useViewTasks must be used within a ViewTaskProvider');
	}
	return context;
};
