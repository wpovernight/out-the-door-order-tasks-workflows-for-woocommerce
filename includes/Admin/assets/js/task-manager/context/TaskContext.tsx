import React, { createContext, useContext, useEffect, useState } from 'react';
import { Task, FieldOption } from '../types/task';
import { fetchTasks, fetchStatus, updateTask } from '../utils/api';

interface TaskContextType {
	tasks: Task[];
	setTasks: React.Dispatch<React.SetStateAction<Task[]>>;
	loadTasks: () => Promise<void>;
	saveTask: (taskId: number, updates: Partial<Task>) => Promise<void>;
	statuses: FieldOption[];
	loadStatuses: () => Promise<void>;
}

const TaskContext = createContext<TaskContextType | undefined>(undefined);

export const TaskProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const [tasks, setTasks] = useState<Task[]>([]);
	const [statuses, setStatuses] = useState<FieldOption[]>([]);

	const loadTasks = async () => {
		try {
			const data = await fetchTasks();
			setTasks(data);
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Failed to fetch tasks:', error);
		}
	};

	const saveTask = async (taskId: number, updates: Partial<Task>) => {
		// try {
		// 	const updated = await updateTask(taskId, updates);
		// 	setTasks(prev =>
		// 		prev.map(t => (t.id === taskId ? { ...t, ...updated } : t))
		// 	);
		// } catch (err) {
		// 	console.error("Failed to update task:", err);
		// }
	};

	const loadStatuses = async () => {
		try {
			const data = await fetchStatus();
			setStatuses(data);
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Failed to fetch columns:', error);
		}
	};

	return (
		<TaskContext.Provider
			value={{
				tasks,
				setTasks,
				loadTasks,
				saveTask,
				statuses,
				loadStatuses,
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
