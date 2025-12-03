import React from 'react';
import SectionHeader from '@orderEdit/components/common/SectionHeader';
import { useOrderEditData } from '@orderEdit/hooks/useOrderEditData';
import { useOrderTask } from '@orderEdit/context/OrderTaskContext';
import { useTaskCreation } from '@shared/hooks/useTaskFormModal';

const TaskSection: React.FC = () => {
	const { i18n } = useOrderEditData();
	const { activeCount, finishedCount } = useOrderTask();
	const { openCreateTaskModal } = useTaskCreation();

	const handleAddTask = (e: React.MouseEvent) => {
		e.preventDefault();
		openCreateTaskModal({ title: i18n.tasks.addTask });
	};

	const totalTasks = activeCount + finishedCount;
	const completionPercentage =
		totalTasks > 0 ? (finishedCount / totalTasks) * 100 : 0;

	return (
		<SectionHeader
			title="Tasks"
			details={`${activeCount} ${i18n.tasks.active}`}
			progressValue={completionPercentage}
			actionButtons={[
				<button
					key="add-task"
					className="wpo-button wpo-button-icon add-button"
					onClick={handleAddTask}
				>
					<span className="screenReader">{i18n.tasks.addTask}</span>
				</button>,
			]}
		/>
	);
};

export default TaskSection;
