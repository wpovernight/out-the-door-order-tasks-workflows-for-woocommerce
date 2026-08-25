import React, { useContext, useMemo } from 'react';
import { Task, useTasks, isTaskArchived } from '@sdk';
import { useView } from '@taskManager/context/ViewContext';

interface ViewTaskContextType {
	archivedTasks: Task[];
}

const ViewTaskContext = React.createContext<ViewTaskContextType | undefined>(
	undefined
);

export const ViewTaskProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const { tasks } = useTasks();
	const { searchQuery } = useView();

	const archivedTasks = useMemo(() => {
		return tasks.filter((task) => {
			if (!isTaskArchived(task)) {
				return false;
			}
			if (!searchQuery) {
				return true;
			}
			const query = searchQuery.toLowerCase();
			return (
				task.title.toLowerCase().includes(query) ||
				(task.description ?? '').toLowerCase().includes(query)
			);
		});
	}, [tasks, searchQuery]);

	return (
		<ViewTaskContext.Provider
			value={{
				archivedTasks,
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
