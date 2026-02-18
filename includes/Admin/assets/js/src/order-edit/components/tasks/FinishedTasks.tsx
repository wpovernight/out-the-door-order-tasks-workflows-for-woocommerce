import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { TaskCard } from '@shared/components/TaskCard';
import { useOrderTask } from '@orderEdit/context/OrderTaskContext';
import { useTaskEdit } from '@shared/hooks/useTaskFormModal';

const FinishedTasks: React.FC = () => {
	const { finishedTasks, finishedCount, deleteTask, loadingStatus } =
		useOrderTask();
	const { openEditTaskModal } = useTaskEdit();
	const [isExpanded, setIsExpanded] = useState(false);

	const handleEditClick = (taskId: number) => {
		const task = finishedTasks.find((t) => t.id === taskId);
		if (!task) {
			return;
		}

		openEditTaskModal({
			task,
			title: __('Edit task', 'wpo-aom'),
		});
	};

	const handleDeleteClick = async (taskId: number) => {
		// ToDo: Update to use custom modal
		// eslint-disable-next-line no-alert
		if (!window.confirm(__('Are you sure?', 'wpo-aom'))) {
			return;
		}

		try {
			await deleteTask(taskId);
		} catch (error) {
			console.error('Failed to delete task:', error);
			// eslint-disable-next-line no-alert
			alert('Failed to delete task. Please try again.');
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
					{__('Completed Tasks', 'wpo-aom')} ({finishedCount})
				</h4>
				<ul className="task-list">
					{finishedTasks.map((task) => (
						<TaskCard
							key={task.id}
							task={task}
							onEditClick={handleEditClick}
							onDeleteClick={handleDeleteClick}
							headingLevel="h5"
							showDescription={true}
							descriptionMaxLength={150}
							tagsPosition="top"
							showOrder={false}
							excludeTags={['status']}
						/>
					))}
				</ul>
			</div>

			<button
				className={`wpo-button finished-tasks-toggle ${isExpanded ? 'expanded' : 'collapsed'}`}
				onClick={() => setIsExpanded(!isExpanded)}
				type="button"
			>
				{isExpanded
					? __('Hide Completed Tasks', 'wpo-aom')
					: __('View Completed Tasks', 'wpo-aom')}{' '}
				({finishedCount})
			</button>
		</div>
	);
};

export default FinishedTasks;
