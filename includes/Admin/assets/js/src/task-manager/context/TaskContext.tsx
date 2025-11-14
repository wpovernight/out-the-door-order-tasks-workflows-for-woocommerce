import React, {
	createContext,
	useContext,
	useState,
	useCallback,
	useRef,
} from 'react';
import { Task, FieldOption } from '@shared/types/task';
import {
	fetchTasks,
	fetchFieldOptions,
	moveTask as moveTaskAPI,
} from '@shared/utils/api';

interface TaskContextType {
	tasks: Task[];
	setTasks: React.Dispatch<React.SetStateAction<Task[]>>;
	loadTasks: (force?: boolean) => Promise<void>;
	saveTask: (taskId: number, updates: Partial<Task>) => Promise<void>;
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
	tasksLoaded: boolean;
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
				fieldOptions,
				setFieldOptions,
				loadFieldOptions,
				tasksLoaded,
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
