import React from 'react';
import { TaskCard } from '@shared/components/TaskCard';
import { useOrderTask } from '@orderEdit/context/OrderTaskContext';
import { useOrderEditData } from '@orderEdit/hooks/useOrderEditData';
import { useTaskEdit } from '@shared/hooks/useTaskFormModal';

const ActiveTasks: React.FC = () => {
	const { activeTasks, deleteTask } = useOrderTask();
	const { i18n } = useOrderEditData();
	const { openEditTaskModal } = useTaskEdit();

	const handleEditClick = (taskId: number) => {
		const task = activeTasks.find((t) => t.id === taskId);
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
		}
	};

	if (activeTasks.length === 0) {
		return (
			<div className="task-list task-list-empty">
				<p>{i18n.tasks.noTasks}</p>
			</div>
		);
	}

	return (
		<div className="task-list-container active-tasks-container">
			<ul className="task-list">
				{activeTasks.map((task) => (
					<li>
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
							headingLevel="h4"
							showDescription={true}
							i18n={{
								edit: i18n.actions.edit,
								delete: i18n.actions.delete,
							}}
						/>
					</li>
				))}
			</ul>
		</div>
	);
};

export default ActiveTasks;
