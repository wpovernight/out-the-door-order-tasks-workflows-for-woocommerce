import React, { useEffect, useState } from 'react';
import { useTasks } from '../../context/TaskContext';
import { Board } from './components/Board';
import { ViewTaskProvider } from './context/ViewTaskContext';

export const KanbanView: React.FC = () => {
	useEffect(() => {
		console.log('KanbanView mounted'); // Debug log
	}, []);

	const { isDataLoaded, loadTasks, loadStatuses } = useTasks();

	type Status = 'idle' | 'loading' | 'error';

	const [status, setStatus] = useState<Status>('idle');
	const [error, setError] = useState<Error | null>(null);

	useEffect(() => {
		let isMounted = true;

		const initialize = async () => {
			if (isDataLoaded) {
				setStatus('idle');
				return;
			}

			setStatus('loading');

			try {
				await Promise.all([loadTasks(), loadStatuses()]);
				if (isMounted) {
					setStatus('idle');
				}
			} catch (err: unknown) {
				if (!isMounted) {
					return;
				}
				setStatus('error');
				setError(
					err instanceof Error
						? err
						: new Error('Unknown initialization error')
				);
			} finally {
				if (isMounted) {
					setStatus('idle');
				}
			}
		};

		initialize();

		return () => {
			isMounted = false;
		};
	}, [isDataLoaded, loadTasks, loadStatuses]);

	if (status === 'loading') {
		return (
			<div className="loading-spinner">
				{(window as any).WPO_AOM_TaskManager.loading}
			</div>
		);
	}

	if (status === 'error') {
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
