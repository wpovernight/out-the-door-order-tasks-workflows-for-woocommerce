import React, { createContext, useContext, useEffect, useState } from 'react';
import { Task, FieldOption } from '@shared/types/task';
import {
	fetchTasks,
	moveTask as moveTaskAPI,
	fetchFieldOptions,
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
	fieldOptionsLoaded: Set<string>;
}

const TaskContext = createContext<TaskContextType | undefined>(undefined);

export const TaskProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const [tasks, setTasks] = useState<Task[]>([]);
	const [tasksLoaded, setTasksLoaded] = useState(false);

	const [fieldOptions, setFieldOptions] = useState<
		Record<string, FieldOption[]>
	>({});
	const [fieldOptionsLoaded, setFieldOptionsLoaded] = useState<Set<string>>(
		new Set()
	);

	const loadTasks = React.useCallback(
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

	const loadFieldOptions = React.useCallback(
		async (fieldSlug: string, force: boolean = false) => {
			if (fieldOptionsLoaded.has(fieldSlug) && !force) {
				return;
			}

			try {
				const data = await fetchFieldOptions(fieldSlug);
				setFieldOptions((prev) => ({
					...prev,
					[fieldSlug]: data,
				}));
				setFieldOptionsLoaded((prev) => new Set(prev).add(fieldSlug));
			} catch (error) {
				console.error(
					`Failed to fetch field options for ${fieldSlug}:`,
					error
				);
			}
		},
		[fieldOptionsLoaded]
	);

	const moveTask = async (
		taskId: number,
		previousTaskId: number | null,
		targetStatusId: number
	) => {
		try {
			const response = await moveTaskAPI(
				taskId,
				previousTaskId,
				targetStatusId
			);
		} catch (error) {
			console.error('Failed to move task:', error);
		}
	};

	const saveTask = async (taskId: number, updates: Partial<Task>) => {
		// implement your update logic
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
				fieldOptionsLoaded,
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
