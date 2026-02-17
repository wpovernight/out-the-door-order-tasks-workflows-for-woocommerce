import React from 'react';
import { Task, isFieldOption } from '@shared/types/task';
import { getTaskDueDate, getFieldValue } from '@shared/utils/fieldUtils';
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
	const dueDate = getTaskDueDate(task);
	const { deleteTask, setTasks } = useTasks();
	const { openEditTaskModal } = useTaskEdit();

	const priorityValue = getFieldValue(task, 'priority');
	const statusValue = getFieldValue(task, 'status');

	const priorityLabel = isFieldOption(priorityValue)
		? priorityValue.label
		: typeof priorityValue === 'string'
			? priorityValue
			: '-';
	const priorityColor = isFieldOption(priorityValue)
		? priorityValue.color
		: undefined;

	const statusLabel = isFieldOption(statusValue)
		? statusValue.label
		: typeof statusValue === 'string'
			? statusValue
			: task.status || '-';
	const statusColor = isFieldOption(statusValue)
		? statusValue.color
		: undefined;

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

	return (
		<tr onClick={() => onTaskClick?.(task)}>
			<td className="task-title">
				<div>{truncateText(task.title, 40)}</div>
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

			<td className="task-due_date">
				<time dateTime={dueDate ? dueDate.toISOString() : ''}>
					{dueDate ? formatDate(dueDate) : '-'}
				</time>
			</td>

			<td className="task-description" title={task.description || ''}>
				{truncateText(task.description || '')}
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
