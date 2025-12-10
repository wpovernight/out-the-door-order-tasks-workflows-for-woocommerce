import React, { useState } from 'react';
import { TaskCard } from '@shared/components/TaskCard';
import { useOrderTask } from '@orderEdit/context/OrderTaskContext';
import { useOrderEditData } from '@orderEdit/hooks/useOrderEditData';
import { useTaskEdit } from '@shared/hooks/useTaskFormModal';
import { AsyncLoaderStatus } from '@shared/hooks/useAsyncLoader';

interface FinishedTasksProps {
	loadingStatus: AsyncLoaderStatus;
}

const FinishedTasks: React.FC<FinishedTasksProps> = ({ loadingStatus }) => {
	const { finishedTasks, finishedCount, deleteTask } = useOrderTask();
	const { i18n } = useOrderEditData();
	const { openEditTaskModal } = useTaskEdit();
	const [isExpanded, setIsExpanded] = useState(false);

	const handleEditClick = (taskId: number) => {
		const task = finishedTasks.find((t) => t.id === taskId);
		if (!task) {
			return;
		}

		openEditTaskModal({
			task,
			title: i18n.actions.editTask,
		});
	};

	const handleDeleteClick = async (taskId: number) => {
		// ToDo: Update to use custom modal
		// eslint-disable-next-line no-alert
		if (!window.confirm(i18n.confirmationText)) {
			return;
		}

		try {
			await deleteTask(taskId);
		} catch (error) {
			console.error('Failed to delete task:', error);
			// ToDo: Improve it
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
					{i18n.tasks.finishedTasksHeading} ({finishedCount})
				</h4>
				<ul className="task-list">
					{finishedTasks.map((task) => (
						<TaskCard
							key={task.id}
							task={task}
							onEditClick={(e) => {
								e.stopPropagation();
								handleEditClick(task.id);
							}}
							onDeleteClick={(e) => {
								e.stopPropagation();
								handleDeleteClick(task.id);
							}}
							actionsDisplayMode="icons"
							headingLevel="h5"
							showDescription={true}
							descriptionMaxLength={150}
							isCompact={true}
							excludeTags={['status']}
							i18n={{
								options: i18n.actions.actions,
								edit: i18n.actions.edit,
								delete: i18n.actions.delete,
								markFinished: i18n.actions.markFinished,
							}}
						/>
					))}
				</ul>
			</div>

			<button
				className={`wpo-button finished-tasks-toggle ${isExpanded ? 'expanded' : 'collapsed'}`}
				onClick={() => setIsExpanded(!isExpanded)}
				type="button"
			>
				{isExpanded ? i18n.tasks.hideFinished : i18n.tasks.viewFinished}{' '}
				({finishedCount})
			</button>
		</div>
	);
};

export default FinishedTasks;
