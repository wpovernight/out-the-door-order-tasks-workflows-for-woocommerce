import React from 'react';
import Header from '@orderEdit/components/tasks/Header';
import ActiveTasks from '@orderEdit/components/tasks/ActiveTasks';
import FinishedTasks from '@orderEdit/components/tasks/FinishedTasks';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';
import { useTasks } from '@shared/context/TaskContext';

const TaskSection: React.FC = () => {
	const { loadTasks, loadTaskFields, loadFieldOptions } = useTasks();

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([
			loadTasks(),
			loadTaskFields(),
			loadFieldOptions('status'),
		]);
	}, [loadTasks, loadTaskFields, loadFieldOptions]);

	return (
		<div className="wpo-aom-metabox-section" id="order-tasks">
			<Header />
			<ActiveTasks
				loadingStatus={loadingStatus}
				loadingError={loadingError}
			/>
			<FinishedTasks loadingStatus={loadingStatus} />
		</div>
	);
};

export default TaskSection;
