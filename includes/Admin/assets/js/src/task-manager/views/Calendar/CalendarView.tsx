import React, { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { useTasks } from '@shared/context/TaskContext';
import { ViewTaskProvider } from './context/ViewTaskContext';
import { CalendarContent } from './components/CalendarContent';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';
import { CalendarSkeleton } from '@taskManager/views/Calendar/components/CalendarSkeleton';

export const CalendarView: React.FC = () => {
	const { loadTasks, loadTaskFields, loadFieldOptions } = useTasks();

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([loadTasks(), loadFieldOptions('status')]);
	}, [loadTasks, loadFieldOptions]);

	// Lazy load - Prefetch form data after calendar is displayed
	useEffect(() => {
		if (loadingStatus === 'loaded') {
			// These run in background, no need to await
			loadFieldOptions('priority');
			loadTaskFields();
		}
	}, [loadingStatus, loadFieldOptions, loadTaskFields]);

	if (loadingStatus === 'loading') {
		return <CalendarSkeleton />;
	}

	// ToDo: Improve error handling UI
	if (loadingStatus === 'error') {
		return <div className="error-message">{__( 'Error loading tasks. Please try again.', 'wpo-aom' )}</div>;
	}

	return (
		<ViewTaskProvider>
			<CalendarContent />
		</ViewTaskProvider>
	);
};
