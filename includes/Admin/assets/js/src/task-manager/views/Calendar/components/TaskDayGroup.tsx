import React, { useState, useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { Task, isFieldOption } from '@shared/types/task';
import {
	getTaskDueDate,
	getFieldValue,
	getCompletedDate,
} from '@shared/utils/fieldUtils';
import TaskRow from './TaskRow';

type SortColumn = 'title' | 'priority' | 'status' | 'dueDate' | 'completedDate';
type SortDirection = 'asc' | 'desc';

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
	const [sortColumn, setSortColumn] = useState<SortColumn>('dueDate');
	const [sortDirection, setSortDirection] = useState<SortDirection>('asc');

	const dayLabel = date
		? date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
		: __('No due date', 'wpo-aom');

	const weekdayLabel = date
		? date.toLocaleDateString('en-US', { weekday: 'long' })
		: null;

	const handleSort = (column: SortColumn) => {
		if (sortColumn === column) {
			setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
		} else {
			setSortColumn(column);
			setSortDirection('asc');
		}
	};

	const sortedTasks = useMemo(() => {
		return [...tasks].sort((a, b) => {
			let aValue: any;
			let bValue: any;

			switch (sortColumn) {
				case 'title':
					aValue = a.title.toLowerCase();
					bValue = b.title.toLowerCase();
					break;

				case 'priority': {
					const aPriority = getFieldValue(a, 'priority');
					const bPriority = getFieldValue(b, 'priority');
					aValue = isFieldOption(aPriority) ? aPriority.position : 1;
					bValue = isFieldOption(bPriority) ? bPriority.position : 1;
					break;
				}

				case 'status': {
					const aStatus = getFieldValue(a, 'status');
					const bStatus = getFieldValue(b, 'status');
					aValue = isFieldOption(aStatus) ? aStatus.position : 1;
					bValue = isFieldOption(bStatus) ? bStatus.position : 1;
					break;
				}

				case 'dueDate': {
					const aDueDate = getTaskDueDate(a);
					const bDueDate = getTaskDueDate(b);
					// Handle null dates (put them at the end)
					if (!aDueDate && !bDueDate) {
						return 0;
					}
					if (!aDueDate) {
						return 1;
					}
					if (!bDueDate) {
						return -1;
					}
					aValue = aDueDate.getTime();
					bValue = bDueDate.getTime();
					break;
				}

				case 'completedDate': {
					const aCompletedDate = getCompletedDate(a);
					const bCompletedDate = getCompletedDate(b);
					// Handle null dates (put them at the end)
					if (!aCompletedDate && !bCompletedDate) {
						return 0;
					}
					if (!aCompletedDate) {
						return 1;
					}
					if (!bCompletedDate) {
						return -1;
					}
					aValue = aCompletedDate.getTime();
					bValue = bCompletedDate.getTime();
					break;
				}

				default:
					return 0;
			}

			if (aValue < bValue) {
				return sortDirection === 'asc' ? -1 : 1;
			}
			if (aValue > bValue) {
				return sortDirection === 'asc' ? 1 : -1;
			}
			return 0;
		});
	}, [tasks, sortColumn, sortDirection]);

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
							<th>
								{__('Due date', 'wpo-aom')}
							</th>
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
