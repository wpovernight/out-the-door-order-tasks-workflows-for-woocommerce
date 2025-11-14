import React, { useContext, useEffect, useState } from 'react';
import { Task } from '@shared/types/task';
import { useTasks } from '../../../context/TaskContext';
import { groupAndSortTasks } from '../../../utils/task-sort';

interface ViewTaskContextType {
	viewTasks: Record<string, Task[]>;
	setViewTasks: React.Dispatch<React.SetStateAction<Record<string, Task[]>>>;
	selectedTask: Task | null;
	selectTask: (task: Task) => void;
	clearSelectedTask: () => void;
}

const ViewTaskContext = React.createContext<ViewTaskContextType | undefined>(
	undefined
);

export const ViewTaskProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const { tasks, fieldOptions, tasksLoaded } = useTasks();
	const [viewTasks, setViewTasks] = useState<Record<string, Task[]>>({});
	const [selectedTask, setSelectedTask] = useState<Task | null>(null);

	const selectTask = (task: Task) => {
		setSelectedTask(task);
	};

	const clearSelectedTask = () => {
		setSelectedTask(null);
	};

	useEffect(() => {
		const statuses = fieldOptions.status || [];
		if (
			!tasksLoaded ||
			!fieldOptions.status ||
			tasks.length === 0 ||
			statuses.length === 0 ||
			Object.keys(viewTasks).length > 0
		) {
			return;
		}

		const grouped = groupAndSortTasks(tasks, statuses);
		setViewTasks(grouped);
	}, [tasks, viewTasks, fieldOptions, tasksLoaded]);

	return (
		<ViewTaskContext.Provider
			value={{
				viewTasks,
				setViewTasks,
				selectedTask,
				selectTask,
				clearSelectedTask,
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
