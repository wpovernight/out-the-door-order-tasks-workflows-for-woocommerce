import React, { createContext, useContext, useEffect, useState } from 'react';
import { Task, FieldOption } from '@shared/types/task';
import { fetchTasks, fetchStatus, moveTask as moveTaskAPI } from '@shared/utils/api';

interface TaskContextType {
	tasks: Task[];
	setTasks: React.Dispatch<React.SetStateAction<Task[]>>;
	statuses: FieldOption[];
	setStatuses: React.Dispatch<React.SetStateAction<FieldOption[]>>;
	loadTasks: (force?: boolean) => Promise<void>;
	loadStatuses: (force?: boolean) => Promise<void>;
	saveTask: (taskId: number, updates: Partial<Task>) => Promise<void>;
	isDataLoaded: boolean;
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
	const [tasks, setTasks] = useState<Task[]>([]);
	const [statuses, setStatuses] = useState<FieldOption[]>([]);
	const [tasksLoaded, setTasksLoaded] = useState(false);
	const [statusesLoaded, setStatusesLoaded] = useState(false);

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

	const loadStatuses = React.useCallback(
		async (force = false) => {
			if (statusesLoaded && !force) {
				return;
			}
			try {
				const data = await fetchStatus();
				setStatuses(data);
				setStatusesLoaded(true);
			} catch (error) {
				console.error('Failed to fetch statuses:', error);
			}
		},
		[statusesLoaded]
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

	const isDataLoaded = tasksLoaded && statusesLoaded;

	return (
		<TaskContext.Provider
			value={{
				tasks,
				setTasks,
				statuses,
				setStatuses,
				loadTasks,
				loadStatuses,
				saveTask,
				isDataLoaded,
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
