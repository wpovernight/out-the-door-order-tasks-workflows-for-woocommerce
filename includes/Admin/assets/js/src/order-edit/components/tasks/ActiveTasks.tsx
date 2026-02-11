import React from 'react';
import { TaskCard } from '@shared/components/TaskCard';
import { useOrderTask } from '@orderEdit/context/OrderTaskContext';
import { useOrderEditData } from '@orderEdit/hooks/useOrderEditData';
import { useTaskEdit, useTaskCreation } from '@shared/hooks/useTaskFormModal';
import { EmptyState, ErrorState } from '@shared/components/LoadingSkeleton';
import { TaskCardSkeleton } from '@shared/components/TaskCardSkeleton';

const ActiveTasks: React.FC = () => {
	const {
		activeTasks,
		deleteTask,
		loadingStatus,
		loadingError,
		refreshTasks,
	} = useOrderTask();
	const { i18n, orderId } = useOrderEditData();
	const { openEditTaskModal } = useTaskEdit();
	const { openCreateTaskModal } = useTaskCreation();

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

	const handleAddTask = () => {
		openCreateTaskModal({ title: i18n.tasks.addTask, orderId });
	};

	// Show loading state
	if (loadingStatus === 'loading') {
		return <TaskCardSkeleton count={1} showDescription={true} />;
	}

	// Show error state
	if (loadingStatus === 'error') {
		return (
			<ErrorState
				message={loadingError?.message || i18n.errorLoading}
				onRetry={refreshTasks}
			/>
		);
	}

	// Show empty state only after data is loaded
	if (loadingStatus === 'loaded' && activeTasks.length === 0) {
		return (
			<EmptyState
				icon="📋"
				message={i18n.tasks.noTasks || 'No active tasks yet.'}
				actionText={i18n.tasks.addTask || 'Add Task'}
				onAction={handleAddTask}
			/>
		);
	}

	return (
		<div className="task-list-container active-tasks-container">
			<h4 className="screenReader">{i18n.tasks.activeTasksHeading}</h4>
			<ul className="task-list">
				{activeTasks.map((task) => (
					<li key={task.id}>
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
							i18n={{
								edit: i18n.actions.edit,
								delete: i18n.actions.delete,
								markFinished: i18n.actions.markFinished,
							}}
						/>
					</li>
				))}
			</ul>
		</div>
	);
};

export default ActiveTasks;
