import React from 'react';
import { __ } from '@wordpress/i18n';
import { Task, useTaskSort, SortIcon } from '@sdk';
import TaskRow from './TaskRow';

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
		: __(
				'No due date',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			);

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
								{__(
									'Task',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}{' '}
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
								{__(
									'Priority',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}{' '}
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
								{__(
									'Status',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}{' '}
								<SortIcon
									column="status"
									sortColumn={sortColumn}
									sortDirection={sortDirection}
								/>
							</th>
							<th className="task-due-date">
								{__(
									'Due',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
							</th>
							<th
								className="task-done-date th-sortable"
								onClick={() => handleSort('doneDate')}
							>
								{__(
									'Done',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
								<SortIcon
									column="doneDate"
									sortColumn={sortColumn}
									sortDirection={sortDirection}
								/>
							</th>
							<th className="task-actions">
								{__(
									'Actions',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
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
