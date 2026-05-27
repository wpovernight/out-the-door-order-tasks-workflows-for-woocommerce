import React, { useEffect } from 'react';
import { useTasks } from '@shared/context/TaskContext';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';
import { ArchiveSkeleton } from '@taskManager/views/Archive/components/ArchiveSkeleton';
import { __ } from '@wordpress/i18n';
import { ViewTaskProvider } from '@taskManager/views/Archive/context/ViewTaskContext';
import { ArchiveContent } from '@taskManager/views/Archive/components/ArchiveContent';

export const ArchiveView: React.FC = () => {
	const { loadTasks, loadTaskFields } = useTasks();

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([loadTasks()]);
	}, [loadTasks]);

	// Lazy load - Prefetch form data after board is displayed
	useEffect(() => {
		if (loadingStatus === 'loaded') {
			loadTaskFields();
		}
	}, [loadingStatus, loadTaskFields]);

	if (loadingStatus === 'loading') {
		return <ArchiveSkeleton />;
	}

	// ToDo: Improve error handling UI
	if (loadingStatus === 'error') {
		return (
			<div className="error-message">
				{__(
					'Error loading tasks. Please try again.',
					'wpo-advanced-order-manager'
				)}
			</div>
		);
	}

	return (
		<>
			<h3 className="screen-reader-text">
				{__('Task Archive', 'wpo-advanced-order-manager')}
			</h3>
			<ViewTaskProvider>
				<ArchiveContent />
			</ViewTaskProvider>
		</>
	);
};
