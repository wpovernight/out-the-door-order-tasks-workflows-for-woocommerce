import React from 'react';
import { Task, isFieldOption } from '@shared/types/task';
import {
	getFieldValue,
	getTaskDateField,
	getFieldObjectValue,
} from '@shared/utils/fieldUtils';
import { formatDate } from '../utils';
import { getColorStyle } from '@shared/utils/colorUtils';
import { __ } from '@wordpress/i18n';
import { truncateText } from '@shared/utils/textUtils';
import { useTasks } from '@shared/context/TaskContext';
import { useTaskEdit } from '@shared/hooks/useTaskFormModal';
import { TaskActionMenu } from '@shared/components/TaskActionMenu';

interface TaskRowProps {
	task: Task;
	onTaskClick?: (task: Task) => void;
}

const TaskRow: React.FC<TaskRowProps> = ({ task, onTaskClick }) => {
	const dueDate = getTaskDateField(task, 'due_date');
	const completedDate = getTaskDateField(task, 'completed_date');
	const { deleteTask, setTasks } = useTasks();
	const { openEditTaskModal } = useTaskEdit();

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
	const statusLabel = getFieldValue(
		task,
		'status',
		'label',
		'-'
	) as string;
	const statusColor = getFieldValue(
		task,
		'status',
		'color',
		undefined
	) as string | undefined;

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
			title: `${__('Edit', 'wpo-aom')}: ${task.title}`,
		});
	};

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
					<h4>{truncateText(task.title, 60)}</h4>
					{task.description ?? (
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

			<td className="task-completed-date">
				<time
					dateTime={completedDate ? completedDate.toISOString() : ''}
				>
					{completedDate ? formatDate(completedDate) : '-'}
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
