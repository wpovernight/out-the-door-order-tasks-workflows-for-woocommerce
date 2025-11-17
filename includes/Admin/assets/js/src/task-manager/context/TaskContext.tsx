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
} from '@shared/utils/api';

interface TaskContextType {
	tasks: Task[];
	setTasks: React.Dispatch<React.SetStateAction<Task[]>>;
	loadTasks: (force?: boolean) => Promise<void>;
	saveTask: (taskId: number, updates: Partial<Task>) => Promise<void>;
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
	tasksLoaded: boolean;
	moveTask: (
		taskId: number,
		previousTaskId: number | null,
		targetStatusId: number
	) => Promise<void>;
	createTask: (taskData: Partial<Task>) => Promise<Task>;
}

const TaskContext = createContext<TaskContextType | undefined>(undefined);

export const TaskProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	// ---------------------
	// TASKS
	// ---------------------
	const [tasks, setTasks] = useState<Task[]>([]);
	const [tasksLoaded, setTasksLoaded] = useState(false);

	const loadTasks = useCallback(
		async (force = false) => {
			if (tasksLoaded && !force) {
				return;
			}

			try {
				const data = await fetchTasks();
				setTasks(data);
				setTasksLoaded(true);
			} catch (error) {
				console.error('Failed to fetch tasks:', error);
			}
		},
		[tasksLoaded]
	);

	// ---------------------
	// FIELD
	// ---------------------
	const [taskFields, setTaskFields] = useState<Record<string, TaskField>>({});
	const [taskFieldsLoaded, setTaskFieldsLoaded] = useState(false);

	const loadTaskFields = useCallback(
		async (force = false) => {
			if (taskFieldsLoaded && !force) {
				return;
			}

			try {
				const data = await fetchTaskFields();
				setTaskFields(data);
				setTaskFieldsLoaded(true);
			} catch (error) {
				console.error('Failed to fetch task fields:', error);
			}
		},
		[taskFieldsLoaded]
	);

	// ---------------------
	// FIELD OPTIONS
	// ---------------------
	const [fieldOptions, setFieldOptions] = useState<
		Record<string, FieldOption[]>
	>({});

	// Track loaded flags in a ref only.
	const loadedOptionsRef = useRef<Set<string>>(new Set());

	/**
	 * Must be stable: empty dependency array.
	 * Must NOT depend on any state object.
	 */
	const loadFieldOptions = useCallback(
		async (fieldSlug: string, force = false) => {
			// Read from ref, not state.
			if (loadedOptionsRef.current.has(fieldSlug) && !force) {
				return;
			}

			try {
				const data = await fetchFieldOptions(fieldSlug);

				// Triggers UI update.
				setFieldOptions((prev) => ({
					...prev,
					[fieldSlug]: data,
				}));

				// Update loaded flag without triggering rerender.
				loadedOptionsRef.current.add(fieldSlug);
			} catch (error) {
				console.error(
					`Failed to fetch field options for ${fieldSlug}:`,
					error
				);
			}
		},
		[]
	); // No dependencies should be added here.

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

	const saveTask = async () => {
		// ToDo: implement later
	};

	return (
		<TaskContext.Provider
			value={{
				tasks,
				setTasks,
				loadTasks,
				saveTask,
				taskFields,
				setTaskFields,
				loadTaskFields,
				fieldOptions,
				setFieldOptions,
				loadFieldOptions,
				tasksLoaded,
				moveTask,
				createTask,
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
