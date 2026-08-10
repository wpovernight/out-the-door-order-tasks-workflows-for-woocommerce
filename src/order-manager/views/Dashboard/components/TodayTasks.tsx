import React, { useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useTasks } from '@sdk/context/TaskContext';
import { TaskCard } from '@sdk/components/TaskCard';
import { useTaskCreation, useTaskEdit } from '@sdk/hooks/useTaskFormModal';
import { useScrollable } from '@sdk/hooks/useScrollable';
import { getTaskDateField, isTaskArchived } from '@sdk/utils/fieldUtils';
import { Task } from '@sdk/types/task';
import { EmptyState } from '@sdk/components/LoadingSkeleton';
import { useConfirm } from '@sdk/context/DialogContext';
import { useStatusRoles } from '@sdk/context/StatusRoleContext';

export const TodayTasks = () => {
	const { tasks, deleteTask } = useTasks();
	const { openCreateTaskModal } = useTaskCreation();
	const { openEditTaskModal } = useTaskEdit();
	const [overdueExpanded, setOverdueExpanded] = useState(false);
	const [doneExpanded, setDoneExpanded] = useState(false);
	const contentRef = useScrollable<HTMLDivElement>();
	const confirm = useConfirm();
	const { statusRoles } = useStatusRoles();

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
		const finish: Task[] = [];

		tasks.forEach((task) => {
			const dueDate = getTaskDateField(task, 'due_date');
			if (!dueDate) {
				return;
			}

			// Skip if is Archived.
			if (isTaskArchived(task)) {
				return;
			}

			const isDone = task.status === statusRoles.done;

			if (isDone && dueDate >= todayStart && dueDate <= todayEnd) {
				finish.push(task);
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

		return { todayActive: active, overdue: over, done: finish };
	}, [tasks, todayStart, todayEnd, statusRoles.done]);

	const handleAddTask = () => {
		openCreateTaskModal({
			title: __('Add Task', 'advanced-order-manager'),
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
		} catch (error) {
			console.error('Failed to delete task:', error);
		}
	};

	const hasNoTasks =
		todayActive.length === 0 && overdue.length === 0 && done.length === 0;

	const renderTaskList = (taskList: Task[], section: string) => (
		<ul className="today-tasks-list">
			{taskList.map((task) => (
				<li key={task.id}>
					<TaskCard
						task={task}
						headingLevel="h5"
						showDescription={section !== 'done'}
						onEditClick={handleEditClick}
						onDeleteClick={handleDeleteClick}
						ActionDisplayMode="inline"
						IncludedActions={['edit', 'archive', 'delete']}
						FinishAsCheckbox={true}
						{...(section === 'done' && {
							tagsPosition: 'none',
							showDueDate: false,
							orderInline: true,
						})}
					/>
				</li>
			))}
		</ul>
	);

	return (
		<div className="dashboard-widget" id="today-tasks">
			<div className="header">
				<h3>
					{__("Today's tasks", 'advanced-order-manager')}{' '}
					<span className="wpo-count-badge">
						{todayActive.length}
					</span>
				</h3>
				<button
					type="button"
					className="wpo-button wpo-button-icon add-new-task"
					onClick={handleAddTask}
					title={__('Add Task', 'advanced-order-manager')}
				>
					<span className="screen-reader-text">
						{__('Add Task', 'advanced-order-manager')}
					</span>
				</button>
			</div>
			<div className="content" ref={contentRef}>
				{hasNoTasks ? (
					<EmptyState
						message={__(
							'No tasks due today.',
							'advanced-order-manager'
						)}
						actionText={__('Add Task', 'advanced-order-manager')}
					/>
				) : (
					<>
						{/* Today's active tasks */}
						{todayActive.length > 0 && (
							<>
								<h4 className="screen-reader-text">
									{__(
										'Active tasks due today',
										'advanced-order-manager'
									)}
								</h4>
								{renderTaskList(todayActive, 'active')}
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
											'advanced-order-manager'
										)}{' '}
										<span className="wpo-count-badge">
											{overdue.length}
										</span>
									</button>
								</h4>
								<div
									className={`today-tasks-list today-tasks-overdue-list ${overdueExpanded ? 'expanded' : 'collapsed'}`}
									id="overdue-tasks-list"
								>
									{renderTaskList(overdue, 'overdue')}
								</div>
							</div>
						)}

						{/* Done tasks */}
						{done.length > 0 && (
							<div
								className={`today-tasks-section ${doneExpanded ? 'expanded' : 'collapsed'}`}
								id="done-tasks-section"
							>
								<h4>
									<button
										type="button"
										className={`today-tasks-section-toggle ${doneExpanded ? 'expanded' : 'collapsed'}`}
										onClick={() =>
											setDoneExpanded(!doneExpanded)
										}
									>
										{__(
											'Tasks marked as done',
											'advanced-order-manager'
										)}{' '}
										<span className="wpo-count-badge">
											{done.length}
										</span>
									</button>
								</h4>
								<div
									className={`today-tasks-list ${doneExpanded ? 'expanded' : 'collapsed'}`}
									id="done-tasks-list"
								>
									{renderTaskList(done, 'done')}
								</div>
							</div>
						)}
					</>
				)}
			</div>
			<div className="footer">
				<a href="#/task-manager/" className="wpo-button view-all-link">
					{__('View all tasks', 'advanced-order-manager')}
				</a>
			</div>
		</div>
	);
};
