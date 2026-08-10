import React from 'react';
import { __ } from '@wordpress/i18n';
import SectionHeader from '@orderEdit/components/common/SectionHeader';
import { useOrderEditData } from '@orderEdit/hooks/useOrderEditData';
import { useOrderTask } from '@orderEdit/context/OrderTaskContext';
import { useTaskCreation } from '@sdk/hooks/useTaskFormModal';

const Header: React.FC = () => {
	const { orderId } = useOrderEditData();
	const { activeCount, finishedCount } = useOrderTask();
	const { openCreateTaskModal } = useTaskCreation();

	const handleAddTask = (e: React.MouseEvent) => {
		e.preventDefault();
		openCreateTaskModal({
			title: __('Add Task', 'advanced-order-manager'),
			initialValues: { orderIds: [orderId] },
		});
	};

	const totalTasks = activeCount + finishedCount;
	const completionPercentage =
		totalTasks > 0 ? (finishedCount / totalTasks) * 100 : null;

	return (
		<SectionHeader
			title={__('Tasks', 'advanced-order-manager')}
			details={`${activeCount} ${__('Active', 'advanced-order-manager')}`}
			progressValue={
				completionPercentage !== null ? completionPercentage : undefined
			}
			actionButtons={[
				<button
					key="add-task"
					className="wpo-button wpo-button-icon add-button"
					onClick={handleAddTask}
				>
					<span className="screen-reader-text">
						{__('Add Task', 'advanced-order-manager')}
					</span>
				</button>,
			]}
		/>
	);
};

export default Header;
