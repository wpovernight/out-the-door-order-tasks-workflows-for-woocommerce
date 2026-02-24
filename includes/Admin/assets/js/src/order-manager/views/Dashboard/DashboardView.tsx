import React, { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { TodayTasks } from '@orderManager/views/Dashboard/components/TodayTasks';
import { useTasks } from '@shared/context/TaskContext';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';
import { SidebarModalProvider } from '@shared/context/SidebarModalContext';
import { TaskCardSkeleton } from '@shared/components/TaskCardSkeleton';
import { ErrorState } from '@shared/components/LoadingSkeleton';

export const DashboardView = () => {
	const { loadTasks, loadTaskFields, loadFieldOptions } = useTasks();

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([
			loadTasks(),
			loadFieldOptions('status'),
			loadFieldOptions('priority'),
		]);
	}, [loadTasks, loadFieldOptions]);

	// Lazy load - Prefetch form data after initial data is displayed
	useEffect(() => {
		if (loadingStatus === 'loaded') {
			loadTaskFields();
		}
	}, [loadingStatus, loadTaskFields]);

	if (loadingStatus === 'loading') {
		return (
			<div className="dashboard-view">
				<div className="dashboard-widget" id="today-tasks">
					<div className="dashboard-widget-header">
						<h3>{__("Today's tasks", 'wpo-aom')}</h3>
					</div>
					<div className="dashboard-widget-content">
						<TaskCardSkeleton count={3} showDescription={true} />
					</div>
				</div>
			</div>
		);
	}

	if (loadingStatus === 'error') {
		return (
			<div className="dashboard-view">
				<ErrorState
					message={
						loadingError?.message ||
						__('Error loading tasks. Please try again.', 'wpo-aom')
					}
				/>
			</div>
		);
	}

	return (
		<>
			<h2 className="screenReader">{__('Dashboard', 'wpo-aom')}</h2>
			<SidebarModalProvider>
				<div className="dashboard-view">
					<TodayTasks />
				</div>
			</SidebarModalProvider>
		</>
	);
};
