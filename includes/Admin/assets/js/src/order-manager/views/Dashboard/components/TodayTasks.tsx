import React, { useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useTasks } from '@shared/context/TaskContext';
import { TaskCard } from '@shared/components/TaskCard';
import { useTaskCreation, useTaskEdit } from '@shared/hooks/useTaskFormModal';
import { useScrollable } from '@shared/hooks/useScrollable';
import { getTaskDateField, isTaskArchived } from '@shared/utils/fieldUtils';
import { TASK_FINISH_STATUS_SLUG, Task } from '@shared/types/task';
import { EmptyState } from '@shared/components/LoadingSkeleton';
import { useConfirm } from '@shared/context/DialogContext';

export const TodayTasks = () => {
	const { tasks, deleteTask } = useTasks();
	const { openCreateTaskModal } = useTaskCreation();
	const { openEditTaskModal } = useTaskEdit();
	const [overdueExpanded, setOverdueExpanded] = useState(true);
	const contentRef = useScrollable<HTMLDivElement>();
	const confirm = useConfirm();

	const todayStart = useMemo(() => {
		const d = new Date();
		d.setHours(0, 0, 0, 0);
		return d;
	}, []);

	const todayEnd = useMemo(() => {
		const d = new Date();
		d.setHours(23, 59, 59, 999);
		return d;
	}, []);

	const { todayActive, overdue, done } = useMemo(() => {
		const active: Task[] = [];
		const over: Task[] = [];
		const done: Task[] = [];

		tasks.forEach((task) => {
			const dueDate = getTaskDateField(task, 'due_date');
			if (!dueDate) {
				return;
			}

			// Skip if is Archived.
			if (isTaskArchived(task)) {
				return;
			}

			const isDone = task.status === TASK_FINISH_STATUS_SLUG;

			if (isDone && dueDate >= todayStart && dueDate <= todayEnd) {
				done.push(task);
			} else if (!isDone && dueDate < todayStart) {
				over.push(task);
			} else if (
				!isDone &&
				dueDate >= todayStart &&
				dueDate <= todayEnd
			) {
				active.push(task);
			}
		});

		return { todayActive: active, overdue: over, done };
	}, [tasks, todayStart, todayEnd]);

	const handleAddTask = () => {
		openCreateTaskModal({
			title: __('Add Task', 'wpo-aom'),
			initialValues: {
				dueDate: new Date().toISOString().split('T')[0],
			},
		});
	};

	const handleEditClick = (taskId: number) => {
		const task = tasks.find((t) => t.id === taskId);
		if (!task) {
			return;
		}

		openEditTaskModal({
			task,
			title: __('Edit task', 'wpo-aom'),
		});
	};

	const handleDeleteClick = async (taskId: number) => {
		const confirmationResult = await confirm({
			title: __('Delete this task?', 'wpo-aom'),
			message: __(
				'Are you sure you want to delete this task?',
				'wpo-aom'
			),
			confirmText: __('Delete', 'wpo-aom'),
			cancelText: __('Cancel', 'wpo-aom'),
			action: 'delete',
		});

		if (!confirmationResult) {
			return;
		}

		try {
			await deleteTask(taskId);
		} catch (error) {
			console.error('Failed to delete task:', error);
		}
	};

	const hasNoTasks = todayActive.length === 0 && overdue.length === 0;

	const renderTaskList = (taskList: Task[]) => (
		<ul className="today-tasks-list">
			{taskList.map((task) => (
				<li key={task.id}>
					<TaskCard
						task={task}
						headingLevel="h5"
						showDescription={true}
						descriptionMaxLength={120}
						onEditClick={handleEditClick}
						onDeleteClick={handleDeleteClick}
						ActionDisplayMode="inline"
						IncludedActions={['edit', 'archive', 'delete']}
						FinishAsCheckbox={true}
					/>
				</li>
			))}
		</ul>
	);

	return (
		<div className="dashboard-widget" id="today-tasks">
			<div className="header">
				<h3>
					{__("Today's tasks", 'wpo-aom')}{' '}
					<span className="wpo-count-badge">
						{todayActive.length}
					</span>
				</h3>
				<button
					type="button"
					className="wpo-button wpo-button-icon wpo-aom-add-button"
					onClick={handleAddTask}
					title={__('Add Task', 'wpo-aom')}
				>
					<span className="screenReader">
						{__('Add Task', 'wpo-aom')}
					</span>
				</button>
			</div>
			<div className="content" ref={contentRef}>
				{hasNoTasks ? (
					<EmptyState
						message={__('No tasks due today.', 'wpo-aom')}
						actionText={__('Add Task', 'wpo-aom')}
					/>
				) : (
					<>
						{/* Today's active tasks */}
						{todayActive.length > 0 && (
							<>
								<h4 className="screenReader">
									{__('Active tasks due today', 'wpo-aom')}
								</h4>
								{renderTaskList(todayActive)}
							</>
						)}

						{/* Overdue tasks */}
						{overdue.length > 0 && (
							<div
								className={`today-tasks-section ${overdueExpanded ? 'expanded' : 'collapsed'}`}
								id="overdue-tasks-section"
							>
								<h4>
									<button
										type="button"
										className={`today-tasks-section-toggle ${overdueExpanded ? 'expanded' : 'collapsed'}`}
										onClick={() =>
											setOverdueExpanded(!overdueExpanded)
										}
									>
										{__(
											'Tasks that are overdue',
											'wpo-aom'
										)}{' '}
										<span className="wpo-count-badge">
											{overdue.length}
										</span>
									</button>
								</h4>
								<div
									className={`today-tasks-overdue-list ${overdueExpanded ? 'expanded' : 'collapsed'}`}
									id="overdue-tasks-list"
								>
									{renderTaskList(overdue)}
								</div>
							</div>
						)}
					</>
				)}
			</div>
			<div className="footer">
				<a href="#/task-manager/" className="wpo-button view-all-link">
					{__('View all tasks', 'wpo-aom')}
				</a>
			</div>
		</div>
	);
};
