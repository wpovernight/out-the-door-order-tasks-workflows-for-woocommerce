import React from 'react';
import { Task, isFieldOption } from '@sdk/types/task';
import {
	getFieldValue,
	getTaskDateField,
	getFieldObjectValue,
} from '@sdk/utils/fieldUtils';
import { formatDate } from '../utils';
import { getColorStyle } from '@sdk/utils/colorUtils';
import { __ } from '@wordpress/i18n';
import { truncateText } from '@sdk/utils/textUtils';
import { useTasks } from '@sdk/context/TaskContext';
import { useTaskEdit } from '@sdk/hooks/useTaskFormModal';
import { TaskActionMenu } from '@sdk/components/TaskActionMenu';
import { useConfirm } from '@sdk/context/DialogContext';

interface TaskRowProps {
	task: Task;
	onTaskClick?: (task: Task) => void;
}

const TaskRow: React.FC<TaskRowProps> = ({ task, onTaskClick }) => {
	const dueDate = getTaskDateField(task, 'due_date');
	const doneDate = getTaskDateField(task, 'done_date');
	const { deleteTask, setTasks } = useTasks();
	const { openEditTaskModal } = useTaskEdit();
	const confirm = useConfirm();

	const priorityLabel = getFieldValue(
		task,
		'priority',
		'label',
		'-'
	) as string;
	const priorityColor = getFieldValue(
		task,
		'priority',
		'color',
		undefined
	) as string | undefined;
	const statusLabel = getFieldValue(task, 'status', 'label', '-') as string;
	const statusColor = getFieldValue(task, 'status', 'color', undefined) as
		| string
		| undefined;

	const handleEdit = () => {
		openEditTaskModal({
			task,
			onTaskSaved: (updatedTask) => {
				setTasks((prevTasks) =>
					prevTasks.map((t) =>
						t.id === updatedTask.id ? updatedTask : t
					)
				);
			},
			title: `${__('Edit', 'advanced-order-manager')}: ${task.title}`,
		});
	};

	const handleDelete = async (taskId: number) => {
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

		deleteTask(taskId);
		setTasks((prevTasks) => prevTasks.filter((t) => t.id !== taskId));
	};

	const renderAssociatedOrder = () => {
		const order = getFieldObjectValue(task, 'order');

		if (!order) {
			return null;
		}

		return (
			<a
				href={order.url as string}
				target="_blank"
				rel="noopener noreferrer"
				onClick={(e) => e.stopPropagation()}
			>
				{order.full_name as string} • #{order.id as number}
			</a>
		);
	};

	return (
		<tr onClick={() => onTaskClick?.(task)}>
			<td className="task-info">
				<div>
					<span>{truncateText(task.title, 60)}</span>
					{task.description && (
						<p>{truncateText(task.description, 90)}</p>
					)}
					{renderAssociatedOrder()}
				</div>
			</td>

			<td className="task-priority">
				<span
					className="wpo-aom-tag"
					style={getColorStyle(priorityColor)}
				>
					{priorityLabel}
				</span>
			</td>

			<td className="task-status">
				<span
					className="wpo-aom-tag"
					style={getColorStyle(statusColor)}
				>
					{statusLabel}
				</span>
			</td>

			<td className="task-due-date">
				<time dateTime={dueDate ? dueDate.toISOString() : ''}>
					{dueDate ? formatDate(dueDate) : '-'}
				</time>
			</td>

			<td className="task-done-date">
				<time dateTime={doneDate ? doneDate.toISOString() : ''}>
					{doneDate ? formatDate(doneDate) : '-'}
				</time>
			</td>

			<td className="task-actions" onClick={(e) => e.stopPropagation()}>
				<TaskActionMenu
					task={task}
					onEdit={handleEdit}
					onDelete={handleDelete}
				/>
			</td>
		</tr>
	);
};

export default TaskRow;
