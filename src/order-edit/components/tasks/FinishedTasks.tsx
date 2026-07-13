import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { TaskCard } from '@shared/components/TaskCard';
import { useOrderTask } from '@orderEdit/context/OrderTaskContext';
import { useTaskEdit } from '@shared/hooks/useTaskFormModal';
import { useConfirm, useNotice } from '@shared/context/DialogContext';

const FinishedTasks: React.FC = () => {
	const { finishedTasks, finishedCount, deleteTask, loadingStatus } =
		useOrderTask();
	const { openEditTaskModal } = useTaskEdit();
	const [isExpanded, setIsExpanded] = useState(false);
	const confirm = useConfirm();
	const notice = useNotice();

	const handleEditClick = (taskId: number) => {
		const task = finishedTasks.find((t) => t.id === taskId);
		if (!task) {
			return;
		}

		openEditTaskModal({
			task,
			title: __('Edit task', 'advanced-order-manager'),
		});
	};

	const handleDeleteClick = async (taskId: number) => {
		const confirmationResult = await confirm({
			title: __('Delete this task?', 'advanced-order-manager'),
			message: __(
				'Are you sure you want to delete this task? This action cannot be undone.',
				'advanced-order-manager'
			),
			confirmText: __('Delete', 'advanced-order-manager'),
			cancelText: __('Cancel', 'advanced-order-manager'),
			action: 'delete',
		});

		if (!confirmationResult) {
			return;
		}

		try {
			await deleteTask(taskId);
		} catch {
			await notice({
				title: __('Delete failed', 'advanced-order-manager'),
				message: __(
					'Failed to delete task. Please try again.',
					'advanced-order-manager'
				),
				action: 'delete',
			});
		}
	};

	// Don't show anything while loading
	if (loadingStatus === 'loading') {
		return null;
	}

	// Hide if no finished tasks after loading
	if (loadingStatus === 'loaded' && finishedCount === 0) {
		return null;
	}

	return (
		<div className="task-list-container finished-tasks-container">
			<div
				className="finished-tasks-list-container"
				style={!isExpanded ? { display: 'none' } : {}}
			>
				<h4>
					{__('Done Tasks', 'advanced-order-manager')} (
					{finishedCount})
				</h4>
				<ul className="task-list">
					{finishedTasks.map((task) => (
						<li key={task.id}>
							<TaskCard
								task={task}
								onEditClick={handleEditClick}
								onDeleteClick={handleDeleteClick}
								headingLevel="h5"
								showDescription={true}
								descriptionMaxLength={150}
								tagsPosition="none"
								showOrder={false}
								excludeTags={['status']}
							/>
						</li>
					))}
				</ul>
			</div>

			<button
				className={`wpo-button finished-tasks-toggle ${isExpanded ? 'expanded' : 'collapsed'}`}
				onClick={() => setIsExpanded(!isExpanded)}
				type="button"
			>
				{isExpanded
					? __('Hide Done Tasks', 'advanced-order-manager')
					: __('View Done Tasks', 'advanced-order-manager')}{' '}
				({finishedCount})
			</button>
		</div>
	);
};

export default FinishedTasks;
