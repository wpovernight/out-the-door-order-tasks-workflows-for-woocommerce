import React from 'react';
import { __ } from '@wordpress/i18n';
import { Task } from '@shared/types/task';
import TaskRow from './TaskRow';
import { SortColumn, useTaskSort } from '@shared/hooks/useTaskSort';

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

	const renderSortIcon = (column: SortColumn) => {
		if (sortColumn !== column) {
			return <span className="calendar-sort-icon">↕</span>;
		}
		return (
			<span className="calendar-sort-icon calendar-sort-icon-active">
				{sortDirection === 'asc' ? '↑' : '↓'}
			</span>
		);
	};

	return (
		<div className="calendar-task-day-group">
			<div className="calendar-task-day-header">
				<h4 className="calendar-task-day-label">{dayLabel}</h4>
				{weekdayLabel && (
					<span className="calendar-task-day-weekday">
						{weekdayLabel}
					</span>
				)}
				<span className="calendar-task-day-count">{tasks.length}</span>
			</div>

			<div className="calendar-task-table-wrapper">
				<table>
					<thead>
						<tr>
							<th
								className="calendar-task-info calendar-task-th-sortable"
								onClick={() => handleSort('title')}
							>
								{__('Task', 'wpo-aom')}{' '}
								{renderSortIcon('title')}
							</th>
							<th
								className="calendar-task-th-sortable"
								onClick={() => handleSort('priority')}
							>
								{__('Priority', 'wpo-aom')}{' '}
								{renderSortIcon('priority')}
							</th>
							<th
								className="calendar-task-th-sortable"
								onClick={() => handleSort('status')}
							>
								{__('Status', 'wpo-aom')}{' '}
								{renderSortIcon('status')}
							</th>
							<th>{__('Due date', 'wpo-aom')}</th>
							<th
								className="calendar-task-th-sortable"
								onClick={() => handleSort('completedDate')}
							>
								{__('Completed at', 'wpo-aom')}
								{renderSortIcon('completedDate')}
							</th>
							<th>{__('Actions', 'wpo-aom')}</th>
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
