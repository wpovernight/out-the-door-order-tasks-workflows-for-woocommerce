import React from 'react';
import { __ } from '@wordpress/i18n';
import SectionHeader from '@orderEdit/components/common/SectionHeader';
import { useOrderEditData } from '@orderEdit/hooks/useOrderEditData';
import { useOrderTask } from '@orderEdit/context/OrderTaskContext';
import { useTaskCreation } from '@shared/hooks/useTaskFormModal';

const Header: React.FC = () => {
	const { orderId } = useOrderEditData();
	const { activeCount, finishedCount } = useOrderTask();
	const { openCreateTaskModal } = useTaskCreation();

	const handleAddTask = (e: React.MouseEvent) => {
		e.preventDefault();
		openCreateTaskModal({ title: __( 'Add Task', 'wpo-aom' ), orderId });
	};

	const totalTasks = activeCount + finishedCount;
	const completionPercentage =
		totalTasks > 0 ? (finishedCount / totalTasks) * 100 : null;

	return (
		<SectionHeader
			title={__( 'Tasks', 'wpo-aom' )}
			details={`${activeCount} ${__( 'Active Tasks', 'wpo-aom' )}`}
			progressValue={
				completionPercentage !== null ? completionPercentage : undefined
			}
			actionButtons={[
				<button
					key="add-task"
					className="wpo-button wpo-button-icon add-button"
					onClick={handleAddTask}
				>
					<span className="screenReader">{__( 'Add Task', 'wpo-aom' )}</span>
				</button>,
			]}
		/>
	);
};

export default Header;
