import React, { useEffect } from 'react';
import { useTasks } from '../../context/TaskContext';
import { Board } from './components/Board';
import { ViewTaskProvider } from './context/ViewTaskContext';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';

export const KanbanView: React.FC = () => {
	const { loadTasks, loadTaskFields, loadFieldOptions } = useTasks();

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([loadTasks(), loadFieldOptions('status')]);
	}, [loadTasks, loadFieldOptions]);

	// Lazy load - Prefetch form data after board is displayed
	useEffect(() => {
		if (loadingStatus === 'loaded') {
			// These run in background, no need to await
			loadFieldOptions('priority');
			loadTaskFields();
		}
	}, [loadingStatus, loadFieldOptions, loadTaskFields]);

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
