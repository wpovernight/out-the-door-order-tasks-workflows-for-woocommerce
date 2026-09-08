import React, { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { useTasks, useAsyncLoader } from '@sdk';
import { Board } from './components/Board';
import { KanbanToolbar } from './components/KanbanToolbar';
import { ViewTaskProvider } from './context/ViewTaskContext';
import { BoardSkeleton } from '@taskManager/views/Kanban/components/BoardSkeleton';

export const KanbanView: React.FC = () => {
	const { loadTasks, loadTaskFields, loadFieldOptions } = useTasks();

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([
			loadTasks(),
			loadFieldOptions('status'),
			loadFieldOptions('priority'),
		]);
	}, [loadTasks, loadFieldOptions]);

	// Lazy load - Prefetch form data after board is displayed
	useEffect(() => {
		if (loadingStatus === 'loaded') {
			loadTaskFields();
		}
	}, [loadingStatus, loadTaskFields]);

	if (loadingStatus === 'loading') {
		return <BoardSkeleton />;
	}

	// ToDo: Improve error handling UI
	if (loadingStatus === 'error') {
		return (
			<div className="error-message">
				{__(
					'Error loading tasks. Please try again.',
					'advanced-order-manager-for-woocommerce'
				)}
			</div>
		);
	}

	return (
		<>
			<h3 className="screen-reader-text">
				{__('Task Board', 'advanced-order-manager-for-woocommerce')}
			</h3>
			<ViewTaskProvider>
				<KanbanToolbar />
				<Board />
			</ViewTaskProvider>
		</>
	);
};
