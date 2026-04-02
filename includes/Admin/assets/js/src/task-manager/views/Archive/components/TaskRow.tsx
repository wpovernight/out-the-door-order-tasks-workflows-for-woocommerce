import React from 'react';
import { Task } from '@shared/types/task';
import {
	getFieldObjectValue,
	getFieldRawValue,
	getFieldValue,
	getTaskDateField,
} from '@shared/utils/fieldUtils';
import { useTasks } from '@shared/context/TaskContext';
import { truncateText } from '@shared/utils/textUtils';
import { formatDate } from '@taskManager/views/Calendar/utils';
import { __ } from '@wordpress/i18n';

interface TaskRowProps {
	task: Task;
}

const TaskRow: React.FC<TaskRowProps> = ({ task }) => {
	const completedDate = getTaskDateField(task, 'completed_date');
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

	const handleDelete = (taskId: number) => {
		// eslint-disable-next-line no-alert
		if (window.confirm(__('Are you sure?', 'wpo-aom'))) {
			deleteTask(taskId);
			setTasks((prevTasks) => prevTasks.filter((t) => t.id !== taskId));
		}
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

			<td className="task-completed-date">
				<time
					dateTime={completedDate ? completedDate.toISOString() : ''}
				>
					{completedDate ? formatDate(completedDate) : '-'}
				</time>
			</td>

			<td className="task-archived-date">
				<time dateTime={archivedDate ? archivedDate.toISOString() : ''}>
					{archivedDate ? formatDate(archivedDate) : '-'}
				</time>
			</td>

			<td className="task-actions">
				<ul className="wpo-aom-row-actions">
					<li>
						<button
							type="button"
							className="wpo-button wpo-button-icon wpo-aom-restore-button"
							onClick={(e) => {
								e.stopPropagation();
								unarchiveTask(task.id);
							}}
							title={__('Restore Task', 'wpo-aom')}
						>
							<span className="screenReader">
								{__('Restore Task', 'wpo-aom')}
							</span>
						</button>
					</li>
					<li>
						<button
							type="button"
							className="wpo-button wpo-button-icon wpo-aom-delete-button"
							onClick={(e) => {
								e.stopPropagation();
								handleDelete(task.id);
							}}
							title={__('Delete Task', 'wpo-aom')}
						>
							<span className="screenReader">
								{__('Delete Task', 'wpo-aom')}
							</span>
						</button>
					</li>
				</ul>
			</td>
		</tr>
	);
};

export default TaskRow;
