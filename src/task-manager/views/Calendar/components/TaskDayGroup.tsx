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
		? date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
		: __('No due date', 'wpo-advanced-order-manager');

	const weekdayLabel = date
		? date.toLocaleDateString(undefined, { weekday: 'long' })
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
								{__('Task', 'wpo-advanced-order-manager')}{' '}
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
								{__('Priority', 'wpo-advanced-order-manager')}{' '}
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
								{__('Status', 'wpo-advanced-order-manager')}{' '}
								<SortIcon
									column="status"
									sortColumn={sortColumn}
									sortDirection={sortDirection}
								/>
							</th>
							<th className="task-due-date">
								{__('Due', 'wpo-advanced-order-manager')}
							</th>
							<th
								className="task-done-date th-sortable"
								onClick={() => handleSort('doneDate')}
							>
								{__('Done', 'wpo-advanced-order-manager')}
								<SortIcon
									column="doneDate"
									sortColumn={sortColumn}
									sortDirection={sortDirection}
								/>
							</th>
							<th className="task-actions">
								{__('Actions', 'wpo-advanced-order-manager')}
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
