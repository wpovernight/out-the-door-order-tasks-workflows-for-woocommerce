import React, { useEffect, useState } from 'react';
import { useTasks } from '../../context/TaskContext';
import { Board } from './components/Board';

export const KanbanView: React.FC = () => {
	const { isInitialized, loadTasks, loadStatuses } = useTasks();
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState<Error | null>(null);

	useEffect(() => {
		const initialize = async () => {
			if (isInitialized) {
				return;
			}
			setLoading(true);
			try {
				await Promise.all([loadTasks(), loadStatuses()]);
			} catch (err: unknown) {
				setError(
					err instanceof Error
						? err
						: new Error('Unknown initialization error')
				);
			} finally {
				setLoading(false);
			}
		};
		console.log('Initializing Kanban View...'); // ToDo: Remove debug log
		initialize();
	}, [isInitialized, loadTasks, loadStatuses]);

	if (loading) {
		return (
			<div className="loading-spinner">
				{(window as any).WPO_AOM_TaskManager.loading}
			</div>
		);
	}

	if (error) {
		return (
			<div className="error-message">
				{(window as any).WPO_AOM_TaskManager.errorLoading}
			</div>
		);
	}

	return <Board />;
};
