import React from 'react';
import {
	Task,
	isFieldOption,
	TASK_FINISH_STATUS_SLUG,
} from '@shared/types/task';
import { getTaskDueDate, getFieldValue } from '@shared/utils/fieldUtils';
import { formatDate } from '../utils';
import { getColorStyle } from '@shared/utils/colorUtils';
import { __ } from '@wordpress/i18n';
import { truncateText } from '@shared/utils/textUtils';
import { useTasks } from '@shared/context/TaskContext';

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
	const { finishTask, unfinishTask } = useTasks();

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

	const isCompleted = task.status === TASK_FINISH_STATUS_SLUG;

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

			<td className="task-actions">
				<ul className="wpo-aom-task-actions">
					<li>
						<button
							className={`task-finish ${isCompleted ? 'finished' : ''}`}
							onClick={(e) => {
								e.stopPropagation();
								if (isCompleted) {
									unfinishTask(task.id);
								} else {
									finishTask(task.id);
								}
							}}
						>
							<span className="screenReader">
								{__( 'Mark as Completed', 'wpo-aom' )}
							</span>
						</button>
					</li>
					<li>
						<button
							className="task-edit"
							onClick={(e) => {
								e.stopPropagation();
								onTaskEdit?.(task);
							}}
						>
							<span className="screenReader">
								{__( 'Edit', 'wpo-aom' )}
							</span>
						</button>
					</li>
					<li>
						<button
							className="task-delete"
							onClick={(e) => {
								e.stopPropagation();
								// ToDo: Update to use custom modal
								// eslint-disable-next-line no-alert
								const confirmed = window.confirm(
									__( 'Are you sure?', 'wpo-aom' )
								);
								if (confirmed) {
									onTaskDelete?.(task.id);
								}
							}}
						>
							<span className="screenReader">
								{__( 'Delete', 'wpo-aom' )}
							</span>
						</button>
					</li>
				</ul>
			</td>
		</tr>
	);
};

export default TaskRow;
