import React from 'react';
import { __ } from '@wordpress/i18n';
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
	const { orderId } = useOrderEditData();
	const { openEditTaskModal } = useTaskEdit();
	const { openCreateTaskModal } = useTaskCreation();

	const handleEditClick = (taskId: number) => {
		const task = activeTasks.find((t) => t.id === taskId);
		if (!task) {
			return;
		}

		openEditTaskModal({
			task,
			title: __( 'Edit task', 'wpo-aom' ),
		});
	};

	const handleDeleteClick = async (taskId: number) => {
		// ToDo: Update to use custom modal
		// eslint-disable-next-line no-alert
		if (!window.confirm(__( 'Are you sure?', 'wpo-aom' ))) {
			return;
		}

		try {
			await deleteTask(taskId);
		} catch (error) {
			console.error('Failed to delete task:', error);
		}
	};

	const handleAddTask = () => {
		openCreateTaskModal({ title: __( 'Add Task', 'wpo-aom' ), orderId });
	};

	// Show loading state
	if (loadingStatus === 'loading') {
		return <TaskCardSkeleton count={1} showDescription={true} />;
	}

	// Show error state
	if (loadingStatus === 'error') {
		return (
			<ErrorState
				message={loadingError?.message || __( 'Error loading data. Please try again.', 'wpo-aom' )}
				onRetry={refreshTasks}
			/>
		);
	}

	// Show empty state only after data is loaded
	if (loadingStatus === 'loaded' && activeTasks.length === 0) {
		return (
			<EmptyState
				icon="📋"
				message={__( 'No tasks found.', 'wpo-aom' )}
				actionText={__( 'Add Task', 'wpo-aom' )}
				onAction={handleAddTask}
			/>
		);
	}

	return (
		<div className="task-list-container active-tasks-container">
			<h4 className="screenReader">{__( 'Active Tasks', 'wpo-aom' )}</h4>
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
						/>
					</li>
				))}
			</ul>
		</div>
	);
};

export default ActiveTasks;
