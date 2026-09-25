import React from 'react';
import {
	Task,
	getFieldObjectValue,
	getFieldRawValue,
	getFieldValue,
	getTaskDateField,
	useTasks,
	truncateText,
	useConfirm,
} from '@sdk';
import { formatDate } from '@taskManager/views/Calendar/utils';
import { __ } from '@wordpress/i18n';

interface TaskRowProps {
	task: Task;
}

const TaskRow: React.FC<TaskRowProps> = ({ task }) => {
	const confirm = useConfirm();

	const doneDate = getTaskDateField(task, 'done_date');
	const archivedDate = getTaskDateField(task, 'archived_date');

	const customerName = getFieldValue(
		task,
		'order',
		'full_name',
		'-'
	) as string;
	const customerProfileLink = getFieldValue(
		task,
		'order',
		'profile_url',
		''
	) as string;

	const { deleteTask, setTasks, unarchiveTask } = useTasks();

	const handleDelete = async (taskId: number) => {
		const confirmationResult = await confirm({
			title: __(
				'Permanently delete this task?',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			message: __(
				'Are you sure you want to delete this task? This action cannot be undone.',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			confirmText: __(
				'Delete',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			cancelText: __(
				'Cancel',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			action: 'delete',
		});

		if (!confirmationResult) {
			return;
		}

		deleteTask(taskId);
		setTasks((prevTasks) => prevTasks.filter((t) => t.id !== taskId));
	};

	const handleRestore = async (taskId: number) => {
		const confirmationResult = await confirm({
			title: __(
				'Restore this task?',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			message: __(
				'Once restored, you can locate this task in the board tab.',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			confirmText: __(
				'Restore',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			cancelText: __(
				'Cancel',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			action: 'restore',
		});

		if (!confirmationResult) {
			return;
		}

		unarchiveTask(task.id);
	};

	const renderAssociatedOrder = () => {
		const order = getFieldObjectValue(task, 'order');

		if (!order) {
			return <span>-</span>;
		}

		return (
			<a
				href={order.url as string}
				target="_blank"
				rel="noopener noreferrer"
				onClick={(e) => e.stopPropagation()}
			>
				#{order.id as number}
			</a>
		);
	};

	return (
		<tr>
			<td className="task-info">
				<div>
					<span>{truncateText(task.title, 60)}</span>
					{task.description && (
						<p>{truncateText(task.description, 90)}</p>
					)}
				</div>
			</td>

			<td className="order-id">{renderAssociatedOrder()}</td>

			<td className="customer-name">
				{customerProfileLink ? (
					<a
						href={customerProfileLink}
						target="_blank"
						rel="noopener noreferrer"
					>
						{customerName}
					</a>
				) : (
					customerName
				)}
			</td>

			<td className="task-done-date">
				<time dateTime={doneDate ? doneDate.toISOString() : ''}>
					{doneDate ? formatDate(doneDate) : '-'}
				</time>
			</td>

			<td className="task-archived-date">
				<time dateTime={archivedDate ? archivedDate.toISOString() : ''}>
					{archivedDate ? formatDate(archivedDate) : '-'}
				</time>
			</td>

			<td className="task-actions">
				<ul className="wpo-otd-row-actions">
					<li>
						<button
							type="button"
							className="wpo-button wpo-button-icon wpo-otd-restore-button"
							onClick={(e) => {
								e.stopPropagation();
								handleRestore(task.id);
							}}
							title={__(
								'Restore Task',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
						>
							<span className="screen-reader-text">
								{__(
									'Restore Task',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
							</span>
						</button>
					</li>
					<li>
						<button
							type="button"
							className="wpo-button wpo-button-icon wpo-otd-delete-button"
							onClick={(e) => {
								e.stopPropagation();
								handleDelete(task.id);
							}}
							title={__(
								'Delete Task',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
						>
							<span className="screen-reader-text">
								{__(
									'Delete Task',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
							</span>
						</button>
					</li>
				</ul>
			</td>
		</tr>
	);
};

export default TaskRow;
