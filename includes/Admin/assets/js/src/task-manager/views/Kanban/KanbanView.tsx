import React, { useEffect } from 'react';
import { useTasks } from '@shared/context/TaskContext';
import { Board } from './components/Board';
import { ViewTaskProvider } from './context/ViewTaskContext';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';
import { useTaskManagerData } from '@taskManager/hooks/useTaskManagerData';

export const KanbanView: React.FC = () => {
	const { loadTasks, loadTaskFields, loadFieldOptions } = useTasks();
	const localized = useTaskManagerData();

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
		return <div className="loading-spinner">{localized.loading}</div>;
	}

	// ToDo: Improve error handling UI
	if (loadingStatus === 'error') {
		return <div className="error-message">{localized.errorLoading}</div>;
	}

	return (
		<ViewTaskProvider>
			<Board />
		</ViewTaskProvider>
	);
};
