import React from 'react';
import { Task, isFieldOption } from '@shared/types/task';
import { getTaskDueDate, getFieldValue } from '@shared/utils/fieldUtils';
import { formatDate } from '../utils';
import { getColorStyle } from '@shared/utils/colorUtils';

interface TaskRowProps {
	task: Task;
	onTaskClick?: (task: Task) => void;
	onTaskEdit?: (task: Task) => void;
	onTaskDelete?: (taskId: number) => void;
}

const TaskRow: React.FC<TaskRowProps> = ({
	task,
	onTaskClick,
	onTaskEdit,
	onTaskDelete,
}) => {
	const dueDate = getTaskDueDate(task);

	const priorityValue = getFieldValue(task, 'priority');
	const statusValue = getFieldValue(task, 'status');

	const truncateText = (text: string, maxLength: number = 140): string => {
		if (!text || text.length <= maxLength) {
			return text;
		}

		// Find the last space within maxLength.
		const truncated = text.substring(0, maxLength);
		const lastSpaceIndex = truncated.lastIndexOf(' ');

		// If there's a space, cut there; otherwise cut at maxLength.
		if (lastSpaceIndex > 0) {
			return truncated.substring(0, lastSpaceIndex).trim() + '...';
		}

		return truncated.trim() + '...';
	};

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

	return (
		<tr onClick={() => onTaskClick?.(task)}>
			<td className="task-title">
				<div>{truncateText(task.title, 40)}</div>
			</td>

			<td className="task-priority">
				<span className="wpo-tag" style={getColorStyle(priorityColor)}>
					{priorityLabel}
				</span>
			</td>

			<td className="task-status">
				<span className="wpo-tag" style={getColorStyle(statusColor)}>
					{statusLabel}
				</span>
			</td>

			<td className="task-due-date">
				<time dateTime={dueDate ? dueDate.toISOString() : ''}>
					{dueDate ? formatDate(dueDate) : '-'}
				</time>
			</td>

			<td className="task-description" title={task.description || ''}>
				{truncateText(task.description || '')}
			</td>

			<td className="task-actions">
				<ul className="task-row-actions">
					<li>
						<button
							className="task-edit"
							onClick={(e) => {
								e.stopPropagation();
								onTaskEdit?.(task);
							}}
						/>
					</li>
					<li>
						<button
							className="task-delete"
							onClick={(e) => {
								e.stopPropagation();
								const confirmed =
									window.confirm(`Are you sure?`);
								if (confirmed) {
									onTaskDelete?.(task.id);
								}
							}}
						/>
					</li>
				</ul>
			</td>
		</tr>
	);
};

export default TaskRow;
