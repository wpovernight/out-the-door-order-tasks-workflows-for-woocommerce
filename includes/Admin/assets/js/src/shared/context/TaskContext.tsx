import React, {
	createContext,
	useContext,
	useState,
	useCallback,
	useRef,
} from 'react';
import {
	Task,
	FieldOption,
	TaskField,
	TASK_FINISH_STATUS_SLUG,
	TASK_UNFINISHED_STATUS_SLUG,
} from '@shared/types/task';
import {
	fetchTasks,
	fetchTaskFields,
	fetchFieldOptions,
	moveTask as moveTaskAPI,
	createTask as createTaskAPI,
	updateTask as updateTaskAPI,
	deleteTask as deleteTaskAPI,
	finishTask as finishTaskAPI,
	archiveTask as archiveTaskAPI,
	unarchiveTask as unarchiveTaskAPI,
} from '@shared/utils/api';
import { updateTaskFields } from '@shared/utils/fieldUtils';

interface TaskContextType {
	tasks: Task[];
	setTasks: React.Dispatch<React.SetStateAction<Task[]>>;
	loadTasks: (force?: boolean) => Promise<void>;
	saveTask: (data: Partial<Task>, taskId?: number) => Promise<Task>;
	deleteTask: (taskId: number) => Promise<void>;
	taskFields: Record<string, TaskField>;
	setTaskFields: React.Dispatch<
		React.SetStateAction<Record<string, TaskField>>
	>;
	loadTaskFields: (force?: boolean) => Promise<void>;
	fieldOptions: Record<string, FieldOption[]>;
	setFieldOptions: React.Dispatch<
		React.SetStateAction<Record<string, FieldOption[]>>
	>;
	loadFieldOptions: (fieldSlug: string, force?: boolean) => Promise<void>;
	moveTask: (
		taskId: number,
		previousTaskId: number | null,
		targetStatusId: number
	) => Promise<void>;
	finishTask: (taskId: number) => Promise<boolean>;
	unfinishTask: (taskId: number) => Promise<boolean>;
	archiveTask: (taskId: number) => Promise<boolean>;
	unarchiveTask: (taskId: number) => Promise<boolean>;
}

const TaskContext = createContext<TaskContextType | undefined>(undefined);

export const TaskProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	// ---------------------
	// TASKS
	// ---------------------
	const [tasks, setTasks] = useState<Task[]>([]);
	const tasksLoadedRef = useRef(false);
	// To prevent race condition when call to fetch is received
	const tasksLoadingRef = useRef(false);

	const loadTasks = useCallback(
		async (force = false) => {
			if ((tasksLoadedRef.current || tasksLoadingRef.current) && !force) {
				return;
			}

			tasksLoadingRef.current = true;

			try {
				const data = await fetchTasks();
				setTasks(data);
				tasksLoadedRef.current = true;
			} catch (error) {
				console.error('Failed to fetch tasks:', error);
			} finally {
				tasksLoadingRef.current = false;
			}
		},
		[] // Stable reference
	);

	const createTask = useCallback(
		async (taskData: Partial<Task>): Promise<Task> => {
			try {
				const newTask = await createTaskAPI(taskData);
				setTasks((prevTasks) => [...prevTasks, newTask]);
				return newTask;
			} catch (error) {
				console.error('Failed to create task:', error);
				throw error;
			}
		},
		[]
	);

	const updateTask = useCallback(
		async (taskId: number, updates: Partial<Task>): Promise<Task> => {
			try {
				const updatedTask = await updateTaskAPI(taskId, updates);
				setTasks((prevTasks) =>
					prevTasks.map((task) =>
						task.id === taskId ? updatedTask : task
					)
				);
				return updatedTask;
			} catch (error) {
				console.error('Failed to update task:', error);
				throw error;
			}
		},
		[]
	);

	const deleteTask = useCallback(
		async (taskId: number): Promise<void> => {
			// Remove task from UI immediately
			setTasks((prevTasks) =>
				prevTasks.filter((task) => task.id !== taskId)
			);

			try {
				await deleteTaskAPI(taskId);
			} catch (error) {
				console.error('Failed to delete task:', error);
				// Revert the update by reloading tasks
				loadTasks(true);
				throw error;
			}
		},
		[loadTasks]
	);

	const saveTask = useCallback(
		async (data: Partial<Task>, taskId?: number): Promise<Task> => {
			try {
				let task;

				if (taskId) {
					task = await updateTask(taskId, data);
				} else {
					task = await createTask(data);
				}

				return task;
			} catch (error) {
				console.error('Failed to save task:', error);
				throw error;
			}
		},
		[createTask, updateTask]
	);

	// ---------------------
	// FIELD
	// ---------------------
	const [taskFields, setTaskFields] = useState<Record<string, TaskField>>({});
	const taskFieldsLoadedRef = useRef(false);
	const taskFieldsLoadingRef = useRef(false);

	const loadTaskFields = useCallback(
		async (force = false) => {
			if (
				(taskFieldsLoadedRef.current || taskFieldsLoadingRef.current) &&
				!force
			) {
				return;
			}

			taskFieldsLoadingRef.current = true;

			try {
				const data = await fetchTaskFields();
				setTaskFields(data);
				taskFieldsLoadedRef.current = true;
			} catch (error) {
				console.error('Failed to fetch task fields:', error);
			} finally {
				taskFieldsLoadingRef.current = false;
			}
		},
		[] // No dependencies - stable reference
	);

	// ---------------------
	// FIELD OPTIONS
	// ---------------------
	/**
	 * Field options are stored in an object where each key is the field slug
	 * and the value is an array of FieldOption objects for that field.
	 */
	const [fieldOptions, setFieldOptions] = useState<
		Record<string, FieldOption[]>
	>({});
	const loadedOptionsRef = useRef<Set<string>>(new Set());
	const loadingOptionsRef = useRef<Set<string>>(new Set());

	const loadFieldOptions = useCallback(
		async (fieldSlug: string, force = false) => {
			// Skip if already loaded or currently loading
			if (
				(loadedOptionsRef.current.has(fieldSlug) ||
					loadingOptionsRef.current.has(fieldSlug)) &&
				!force
			) {
				return;
			}

			loadingOptionsRef.current.add(fieldSlug);

			try {
				const data = await fetchFieldOptions(fieldSlug);
				setFieldOptions((prev) => ({
					...prev,
					[fieldSlug]: data,
				}));
				loadedOptionsRef.current.add(fieldSlug);
			} catch (error) {
				console.error(
					`Failed to fetch field options for ${fieldSlug}:`,
					error
				);
			} finally {
				loadingOptionsRef.current.delete(fieldSlug);
			}
		},
		[] // No dependencies - stable reference
	);

	// ---------------------
	// MOVE TASK
	// ---------------------
	// Track pending move operations globally to prevent race conditions.
	const pendingMovesCountRef = useRef<number>(0);
	const pendingPositionUpdatesRef = useRef<Map<number, number>>(new Map());

	const moveTask = useCallback(
		async (
			taskId: number,
			previousTaskId: number | null,
			targetStatusId: number
		): Promise<void> => {
			let previousState: Task[] | null = null;
			let optimisticPosition: number;

			const targetStatusOption = fieldOptions.status?.find(
				(opt) => opt.id === targetStatusId
			);

			if (!targetStatusOption) {;
				console.error('Target status option not found');
				return;
			}

			// Increment global pending moves counter
			pendingMovesCountRef.current += 1;

			// Optimistically update the global state immediately before API call
			setTasks((prevTasks) => {
				previousState = structuredClone(prevTasks);

				// Calculate optimistic position
				optimisticPosition = 0;
				if (previousTaskId !== null) {
					const previousTask = prevTasks.find(
						(t) => t.id === previousTaskId
					);
					if (previousTask && previousTask.position !== undefined) {
						optimisticPosition = previousTask.position + 0.0001;
					}
				}

				return prevTasks.map((task) => {
					if (task.id !== taskId) {
						return task;
					}

					const fieldUpdates: Record<
						string,
						{ raw: any; resolved: any }
					> = {
						status: {
							raw: targetStatusOption.id,
							resolved: targetStatusOption,
						},
						position: {
							raw: optimisticPosition,
							resolved: null,
						},
					};

					if (targetStatusOption.slug === TASK_FINISH_STATUS_SLUG) {
						fieldUpdates.completed_date = {
							raw: new Date()
								.toISOString()
								.replace('T', ' ')
								.slice(0, 19),
							resolved: null,
						};
					} else if (task.status === TASK_FINISH_STATUS_SLUG) {
						fieldUpdates.completed_date = {
							raw: null,
							resolved: null,
						};
					}

					return updateTaskFields(task, fieldUpdates);
				});
			});

			try {
				const result = await moveTaskAPI(
					taskId,
					previousTaskId,
					targetStatusId
				);

				// Collect the position update
				pendingPositionUpdatesRef.current.set(
					taskId,
					result.new_position
				);

				// Decrement pending counter
				pendingMovesCountRef.current -= 1;

				// Only apply position updates when all pending moves are complete
				// This prevents race conditions when multiple tasks are moved quickly
				if (pendingMovesCountRef.current === 0) {
					const positionUpdates = new Map(
						pendingPositionUpdatesRef.current
					);
					pendingPositionUpdatesRef.current.clear();

					// Apply all collected position updates at once
					setTasks((prevTasks) =>
						prevTasks.map((task) => {
							const newPosition = positionUpdates.get(task.id);
							if (newPosition !== undefined) {
								return updateTaskFields(task, {
									position: {
										raw: newPosition,
										resolved: null,
									},
								});
							}
							return task;
						})
					);
				}
			} catch (error) {
				console.error('Failed to move task:', error);

				// Decrement pending counter even on error
				pendingMovesCountRef.current -= 1;

				// Remove from pending updates
				pendingPositionUpdatesRef.current.delete(taskId);

				// Rollback if API call failed
				if (previousState) {
					setTasks(previousState);
				}

				throw error;
			}
		},
		[fieldOptions]
	);

	const finishTask = useCallback(
		async (taskId: number): Promise<boolean> => {
			let previousState: Task[] | null = null;

			// Get the finish status option ID
			const finishedOption = fieldOptions.status?.find(
				(opt) => opt.slug === TASK_FINISH_STATUS_SLUG
			);

			if (!finishedOption) {
				console.error('Finished status option not found');
				return false;
			}

			// Update the UI immediately before API call.
			setTasks((prevTasks) => {
				previousState = structuredClone(prevTasks);

				return prevTasks.map((task) => {
					if (task.id !== taskId) {
						return task;
					}

					return updateTaskFields(task, {
						status: {
							raw: finishedOption.id,
							resolved: finishedOption,
						},
						position: {
							raw: Number.MAX_SAFE_INTEGER,
							resolved: null,
						},
						completed_date: {
							raw: new Date()
								.toISOString()
								.replace('T', ' ')
								.slice(0, 19),
							resolved: null,
						},
					});
				});
			});

			try {
				const result = await finishTaskAPI(taskId);

				if (!result) {
					// Rollback if API call failed
					if (previousState) {
						setTasks(previousState);
					}
				}
				return result;
			} catch (error) {
				console.error('Failed to finish task:', error);

				// Rollback if API call failed
				if (previousState) {
					setTasks(previousState);
				}

				throw error;
			}
		},
		[fieldOptions]
	);

	const unfinishTask = useCallback(
		async (taskId: number): Promise<boolean> => {
			let previousState: Task[] | null = null;

			// Get the default "unfinished" status option.
			const unfinishedOption = fieldOptions.status?.find(
				(fo) => fo.slug === TASK_UNFINISHED_STATUS_SLUG
			);

			if (!unfinishedOption) {
				console.error('Unfinished status option not found');
				return false;
			}

			// Update the UI immediately before API call.
			setTasks((prevTasks) => {
				previousState = structuredClone(prevTasks);

				return prevTasks.map((task) => {
					if (task.id !== taskId) {
						return task;
					}

					return updateTaskFields(task, {
						status: {
							raw: unfinishedOption.id,
							resolved: unfinishedOption,
						},
						position: {
							raw: Number.MAX_SAFE_INTEGER,
							resolved: null,
						},
						completed_date: { raw: null, resolved: null },
					});
				});
			});

			try {
				await moveTaskAPI(taskId, null, unfinishedOption.id);

				return true;
			} catch (error) {
				console.error('Failed to unfinish task:', error);

				// Rollback if API call failed
				if (previousState) {
					setTasks(previousState);
				}

				throw error;
			}
		},
		[fieldOptions]
	);

	const archiveTask = useCallback(
		async (taskId: number): Promise<boolean> => {
			let previousState: Task[] | null = null;

			// Update the UI immediately before API call.
			setTasks((prevTasks) => {
				previousState = structuredClone(prevTasks);

				return prevTasks.map((task) => {
					if (task.id !== taskId) {
						return task;
					}

					return updateTaskFields(task, {
						archived_date: {
							raw: new Date()
								.toISOString()
								.replace('T', ' ')
								.slice(0, 19),
							resolved: null,
						},
					});
				});
			});

			try {
				const result = await archiveTaskAPI(taskId);

				if (!result) {
					// Rollback if API call failed
					if (previousState) {
						setTasks(previousState);
					}
				}
				return result;
			} catch (error) {
				console.error('Failed to archive task:', error);

				// Rollback if API call failed
				if (previousState) {
					setTasks(previousState);
				}

				throw error;
			}
		},
		[]
	);

	const unarchiveTask = useCallback(
		async (taskId: number): Promise<boolean> => {
			let previousState: Task[] | null = null;

			// Update the UI immediately before API call.
			setTasks((prevTasks) => {
				previousState = structuredClone(prevTasks);

				return prevTasks.map((task) => {
					if (task.id !== taskId) {
						return task;
					}

					return updateTaskFields(task, {
						archived_date: { raw: null, resolved: null },
					});
				});
			});

			try {
				await unarchiveTaskAPI(taskId);

				return true;
			} catch (error) {
				console.error('Failed to unarchive task:', error);

				// Rollback if API call failed
				if (previousState) {
					setTasks(previousState);
				}

				throw error;
			}
		},
		[]
	);

	return (
		<TaskContext.Provider
			value={{
				tasks,
				setTasks,
				loadTasks,
				saveTask,
				deleteTask,
				taskFields,
				setTaskFields,
				loadTaskFields,
				fieldOptions,
				setFieldOptions,
				loadFieldOptions,
				moveTask,
				finishTask,
				unfinishTask,
				archiveTask,
				unarchiveTask,
			}}
		>
			{children}
		</TaskContext.Provider>
	);
};

export const useTasks = (): TaskContextType => {
	const context = useContext(TaskContext);
	if (!context) {
		throw new Error('useTasks must be used within a TaskProvider');
	}
	return context;
};
