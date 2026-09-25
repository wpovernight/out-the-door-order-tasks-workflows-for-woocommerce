import React from 'react';
import { __ } from '@wordpress/i18n';
import SectionHeader from '@orderEdit/components/common/SectionHeader';
import { useOrderEditData } from '@orderEdit/hooks/useOrderEditData';
import { useOrderTask } from '@orderEdit/context/OrderTaskContext';
import { useTaskCreation } from '@sdk';

const Header: React.FC = () => {
	const { orderId } = useOrderEditData();
	const { activeCount, finishedCount } = useOrderTask();
	const { openCreateTaskModal } = useTaskCreation();

	const handleAddTask = (e: React.MouseEvent) => {
		e.preventDefault();
		openCreateTaskModal({
			title: __('Add Task', 'out-the-door-order-tasks-workflows-for-woocommerce'),
			initialValues: { orderIds: [orderId] },
		});
	};

	const totalTasks = activeCount + finishedCount;
	const completionPercentage =
		totalTasks > 0 ? (finishedCount / totalTasks) * 100 : null;

	return (
		<SectionHeader
			title={__('Tasks', 'out-the-door-order-tasks-workflows-for-woocommerce')}
			details={`${activeCount} ${__('Active', 'out-the-door-order-tasks-workflows-for-woocommerce')}`}
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
						{__('Add Task', 'out-the-door-order-tasks-workflows-for-woocommerce')}
					</span>
				</button>,
			]}
		/>
	);
};

export default Header;
