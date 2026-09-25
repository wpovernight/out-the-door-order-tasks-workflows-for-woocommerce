import React, { useContext, useEffect, useState, useCallback } from 'react';
import { applyFilters } from '@wordpress/hooks';
import { Task, useTasks, isTaskArchived, useStatusRoles } from '@sdk';
import { groupAndSortTasks } from '../../../utils/task-sort';
import { useView } from '@taskManager/context/ViewContext';

export type BoardControls = Record<string, unknown>;

interface ViewTaskContextType {
	viewTasks: Record<string, Task[]>;
	setViewTasks: React.Dispatch<React.SetStateAction<Record<string, Task[]>>>;
	boardControls: BoardControls;
	setBoardControls: React.Dispatch<React.SetStateAction<BoardControls>>;
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
	const { searchQuery } = useView();
	const [viewTasks, setViewTasks] = useState<Record<string, Task[]>>({});
	const [boardControls, setBoardControls] = useState<BoardControls>({});
	const [selectedTask, setSelectedTask] = useState<Task | null>(null);
	const { statusRoles } = useStatusRoles();

	const selectTask = useCallback((task: Task) => {
		setSelectedTask(task);
	}, []);

	const clearSelectedTask = useCallback(() => {
		setSelectedTask(null);
	}, []);

	// Wrapper function that updates both global state and local viewTasks.
	const finishTask = useCallback(
		async (taskId: number): Promise<boolean> => {
			const doneOptionId = statusRoles.done;
			if (doneOptionId === null) {
				return false;
			}

			const doneOption = fieldOptions.status?.find(
				(opt) => opt.id === doneOptionId
			);
			if (!doneOption) {
				return false;
			}

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

				// Add task to the "done" column if it exists in the view.
				if (taskToMove && updated[doneOption.slug]) {
					const doneTask: Task = {
						...taskToMove,
						status: doneOptionId,
					};

					updated[doneOption.slug].push(doneTask);
				}

				return updated;
			});

			try {
				const success = await globalFinishTask(taskId);

				if (!success && previousState) {
					// Rollback if API call failed.
					setViewTasks(previousState);
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
		[globalFinishTask, statusRoles.done, fieldOptions.status]
	);

	// Wrapper function for unfinishing tasks
	const unfinishTask = useCallback(
		async (taskId: number): Promise<boolean> => {
			const undoneOptionId = statusRoles.undone;
			if (undoneOptionId === null) {
				return false;
			}

			const undoneOption = fieldOptions.status?.find(
				(opt) => opt.id === undoneOptionId
			);
			if (!undoneOption) {
				return false;
			}

			let previousState: Record<string, Task[]> | null = null;
			let taskToMove: Task | null = null;

			// Update UI immediately before API call.
			setViewTasks((prev) => {
				previousState = structuredClone(prev);
				const updated = structuredClone(prev);

				// Find and remove the task from whichever column it's in.
				for (const status in updated) {
					const taskIndex = updated[status].findIndex(
						(t) => t.id === taskId
					);
					if (taskIndex !== -1) {
						[taskToMove] = updated[status].splice(taskIndex, 1);
						break;
					}
				}

				// Add task to the "undone" column if it exists in the view.
				if (taskToMove && updated[undoneOption.slug]) {
					const unfinishedTask: Task = {
						...taskToMove,
						status: undoneOptionId,
					};

					updated[undoneOption.slug].push(unfinishedTask);
				}

				return updated;
			});

			try {
				const success = await globalUnfinishTask(taskId);

				if (!success && previousState) {
					// Rollback if API call failed.
					setViewTasks(previousState);
				}

				return success;
			} catch (error) {
				console.error('Failed to unfinish task:', error);

				// Rollback on error.
				if (previousState) {
					setViewTasks(previousState);
				}

				throw error;
			}
		},
		[globalUnfinishTask, statusRoles.undone, fieldOptions.status]
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

		const query = searchQuery.toLowerCase();
		const activeTasks = tasks.filter((task) => {
			if (isTaskArchived(task)) {
				return false;
			}
			if (!query) {
				return true;
			}
			return (
				task.title.toLowerCase().includes(query) ||
				(task.description ?? '').toLowerCase().includes(query)
			);
		});
		const grouped = applyFilters(
			'wpo_otd.kanban_view_tasks',
			groupAndSortTasks(activeTasks, statuses),
			{ tasks: activeTasks, statuses, controls: boardControls }
		) as Record<string, Task[]>;

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
	}, [tasks, fieldOptions, searchQuery, boardControls]);

	return (
		<ViewTaskContext.Provider
			value={{
				viewTasks,
				setViewTasks,
				boardControls,
				setBoardControls,
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
