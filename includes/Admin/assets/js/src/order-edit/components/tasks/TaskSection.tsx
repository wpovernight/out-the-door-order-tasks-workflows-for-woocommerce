import React, { useEffect } from 'react';
import Header from '@orderEdit/components/tasks/Header';
import ActiveTasks from '@orderEdit/components/tasks/ActiveTasks';
import FinishedTasks from '@orderEdit/components/tasks/FinishedTasks';
import { useOrderTask } from '@orderEdit/context/OrderTaskContext';

const TaskSection: React.FC = () => {
	const { loadTaskData } = useOrderTask();

	useEffect(() => {
		loadTaskData();
	}, [loadTaskData]);

	return (
		<div className="wpo-aom-metabox-section" id="order-tasks">
			<Header />
			<ActiveTasks />
			<FinishedTasks />
		</div>
	);
};

export default TaskSection;
