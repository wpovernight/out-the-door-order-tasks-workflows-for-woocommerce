import React from 'react';
import TaskSection from '@orderEdit/components/tasks/TaskSection';
import ActiveTasks from '@orderEdit/components/tasks/ActiveTasks';
import FinishedTasks from '@orderEdit/components/tasks/FinishedTasks';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';
import { useTasks } from '@shared/context/TaskContext';

const TaskList: React.FC = () => {
	const { loadTasks, loadTaskFields, loadFieldOptions } = useTasks();

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([
			loadTasks(),
			loadTaskFields(),
			loadFieldOptions('status'),
		]);
	}, [loadTasks, loadTaskFields, loadFieldOptions]);

	return (
		<div id="order-tasks">
			<TaskSection />
			<ActiveTasks />
			<FinishedTasks />
		</div>
	);
};

export default TaskList;
