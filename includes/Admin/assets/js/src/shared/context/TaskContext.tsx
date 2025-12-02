import React, {
	createContext,
	useContext,
	useState,
	useCallback,
	useRef,
} from 'react';
import { Task, FieldOption, TaskField } from '@shared/types/task';
import {
	fetchTasks,
	fetchTaskFields,
	fetchFieldOptions,
	moveTask as moveTaskAPI,
	createTask as createTaskAPI,
	updateTask as updateTaskAPI,
	deleteTask as deleteTaskAPI,
} from '@shared/utils/api';

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

	const loadTasks = useCallback(
		async (force = false) => {
			if (tasksLoadedRef.current && !force) {
				return;
			}

			try {
				const data = await fetchTasks();
				setTasks(data);
				tasksLoadedRef.current = true;
			} catch (error) {
				console.error('Failed to fetch tasks:', error);
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

	const deleteTask = useCallback(async (taskId: number): Promise<void> => {
		try {
			await deleteTaskAPI(taskId);
			setTasks((prevTasks) => prevTasks.filter((task) => task.id !== taskId));
		} catch (error) {
			console.error('Failed to delete task:', error);
			throw error;
		}
	}, []);

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
	const moveTask = async (
		taskId: number,
		previousTaskId: number | null,
		targetStatusId: number
	) => {
		try {
			await moveTaskAPI(taskId, previousTaskId, targetStatusId);
		} catch (error) {
			console.error('Failed to move task:', error);
		}
	};

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
