import React, { useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	useTasks,
	TaskCard,
	useTaskCreation,
	useTaskEdit,
	useScrollable,
	getTaskDateField,
	isTaskArchived,
	Task,
	EmptyState,
	useConfirm,
	useStatusRoles,
} from '@sdk';

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
			title: __('Add Task', 'out-the-door-order-tasks-workflows-for-woocommerce'),
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
			title: __('Edit task', 'out-the-door-order-tasks-workflows-for-woocommerce'),
		});
	};

	const handleDeleteClick = async (taskId: number) => {
		const confirmationResult = await confirm({
			title: __('Delete this task?', 'out-the-door-order-tasks-workflows-for-woocommerce'),
			message: __(
				'Are you sure you want to delete this task? This action cannot be undone.',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			confirmText: __('Delete', 'out-the-door-order-tasks-workflows-for-woocommerce'),
			cancelText: __('Cancel', 'out-the-door-order-tasks-workflows-for-woocommerce'),
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
					{__("Today's tasks", 'out-the-door-order-tasks-workflows-for-woocommerce')}{' '}
					<span className="wpo-count-badge">
						{todayActive.length}
					</span>
				</h3>
				<button
					type="button"
					className="wpo-button wpo-button-icon add-new-task"
					onClick={handleAddTask}
					title={__('Add Task', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				>
					<span className="screen-reader-text">
						{__('Add Task', 'out-the-door-order-tasks-workflows-for-woocommerce')}
					</span>
				</button>
			</div>
			<div className="content" ref={contentRef}>
				{hasNoTasks ? (
					<EmptyState
						message={__(
							'No tasks due today.',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)}
						actionText={__('Add Task', 'out-the-door-order-tasks-workflows-for-woocommerce')}
					/>
				) : (
					<>
						{/* Today's active tasks */}
						{todayActive.length > 0 && (
							<>
								<h4 className="screen-reader-text">
									{__(
										'Active tasks due today',
										'out-the-door-order-tasks-workflows-for-woocommerce'
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
											'out-the-door-order-tasks-workflows-for-woocommerce'
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
											'out-the-door-order-tasks-workflows-for-woocommerce'
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
					{__('View all tasks', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</a>
			</div>
		</div>
	);
};
