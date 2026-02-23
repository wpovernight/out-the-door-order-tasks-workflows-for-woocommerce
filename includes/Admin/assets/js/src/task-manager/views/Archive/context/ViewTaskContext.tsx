import React, { useContext, useMemo } from 'react';
import { Task } from '@shared/types/task';
import { useTasks } from '@shared/context/TaskContext';
import { isTaskArchived } from '@shared/utils/fieldUtils';

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

	const archivedTasks = useMemo(() => {
		return tasks.filter((task) => isTaskArchived(task));
	}, [tasks]);

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
