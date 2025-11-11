import React, { useEffect } from 'react';
import { useTasks } from '../../context/TaskContext';
import { Board } from './components/Board';
import { ViewTaskProvider } from './context/ViewTaskContext';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';

export const KanbanView: React.FC = () => {
	useEffect(() => {
		console.log('KanbanView mounted'); // Debug log
	}, []);

	const { loadTasks, loadFieldOptions } = useTasks();

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([loadTasks(), loadFieldOptions('status')]);
	}, [loadTasks, loadFieldOptions]);

	// ToDo: Use a skeleton loader instead of a simple loading spinner
	if (loadingStatus === 'loading') {
		return (
			<div className="loading-spinner">
				{(window as any).WPO_AOM_TaskManager.loading}
			</div>
		);
	}

	// ToDo: Improve error handling UI
	if (loadingStatus === 'error') {
		return (
			<div className="error-message">
				{(window as any).WPO_AOM_TaskManager.errorLoading}
			</div>
		);
	}

	return (
		<ViewTaskProvider>
			<Board />
		</ViewTaskProvider>
	);
};
