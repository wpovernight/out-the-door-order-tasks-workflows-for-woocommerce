import React from 'react';
import { __ } from '@wordpress/i18n';
import { Task } from '@shared/types/task';
import TaskRow from './TaskRow';
import { useTaskSort } from '@shared/hooks/useTaskSort';
import SortIcon from '@shared/components/SortIcon';

interface TaskDayGroupProps {
	date: Date | null;
	tasks: Task[];
	onTaskClick?: (task: Task) => void;
}

const TaskDayGroup: React.FC<TaskDayGroupProps> = ({
	date,
	tasks,
	onTaskClick,
}) => {
	const { sortColumn, sortDirection, handleSort, sortedTasks } =
		useTaskSort(tasks);

	const dayLabel = date
		? date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
		: __('No due date', 'wpo-aom');

	const weekdayLabel = date
		? date.toLocaleDateString('en-US', { weekday: 'long' })
		: null;

	return (
		<div className="calendar-task-day-group">
			<div className="calendar-task-day-header">
				<h5 className="calendar-task-day-label">{dayLabel}</h5>
				{weekdayLabel && (
					<span className="calendar-task-day-weekday">
						{weekdayLabel}
					</span>
				)}
				<span className="wpo-count-badge">{tasks.length}</span>
			</div>

			<div className="calendar-task-table-wrapper">
				<table>
					<thead>
						<tr>
							<th
								className="task-info th-sortable"
								onClick={() => handleSort('title')}
							>
								{__('Task', 'wpo-aom')}{' '}
								<SortIcon
									column="title"
									sortColumn={sortColumn}
									sortDirection={sortDirection}
								/>
							</th>
							<th
								className="task-priority th-sortable"
								onClick={() => handleSort('priority')}
							>
								{__('Priority', 'wpo-aom')}{' '}
								<SortIcon
									column="priority"
									sortColumn={sortColumn}
									sortDirection={sortDirection}
								/>
							</th>
							<th
								className="task-status th-sortable"
								onClick={() => handleSort('status')}
							>
								{__('Status', 'wpo-aom')}{' '}
								<SortIcon
									column="status"
									sortColumn={sortColumn}
									sortDirection={sortDirection}
								/>
							</th>
							<th className="task-due-date">
								{__('Due date', 'wpo-aom')}
							</th>
							<th
								className="task-completed-date th-sortable"
								onClick={() => handleSort('completedDate')}
							>
								{__('Completed at', 'wpo-aom')}
								<SortIcon
									column="completedDate"
									sortColumn={sortColumn}
									sortDirection={sortDirection}
								/>
							</th>
							<th className="task-actions">
								{__('Actions', 'wpo-aom')}
							</th>
						</tr>
					</thead>
					<tbody>
						{sortedTasks.map((task) => (
							<TaskRow
								key={task.id}
								task={task}
								onTaskClick={onTaskClick}
							/>
						))}
					</tbody>
				</table>
			</div>
		</div>
	);
};

export default TaskDayGroup;
