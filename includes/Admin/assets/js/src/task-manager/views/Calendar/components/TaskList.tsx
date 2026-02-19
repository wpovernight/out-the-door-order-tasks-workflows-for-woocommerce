import React, { useState, useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { Task, isFieldOption } from '@shared/types/task';
import TaskRow from './TaskRow';
import {
	getTaskDueDate,
	getFieldValue,
	getCompletedDate,
} from '@shared/utils/fieldUtils';
import { formatDate, DateRange, DateRangePreset } from '../utils';

type SortColumn = 'title' | 'priority' | 'status' | 'dueDate' | 'completedDate';
type SortDirection = 'asc' | 'desc';

interface TaskListProps {
	tasks: Task[];
	dateRange: DateRange;
	dateRangePreset: DateRangePreset;
	onTaskClick?: (task: Task) => void;
}

const TaskList: React.FC<TaskListProps> = ({
	tasks,
	dateRange,
	dateRangePreset,
	onTaskClick,
}) => {
	const [sortColumn, setSortColumn] = useState<SortColumn>('dueDate');
	const [sortDirection, setSortDirection] = useState<SortDirection>('asc');

	// Generate dynamic title based on preset or date range
	const getTitle = (): string => {
		const taskCount = tasks.length;
		const taskCountText =
			taskCount === 1
				? `1 ${__('Task', 'wpo-aom').toLowerCase()}`
				: `${taskCount} ${__('Tasks', 'wpo-aom')}`;

		if (dateRangePreset !== 'custom') {
			const presetLabels: Record<string, string> = {
				today: __('Today', 'wpo-aom'),
				yesterday: __('Yesterday', 'wpo-aom'),
				'current-week': __('Current Week', 'wpo-aom'),
				'next-week': __('Next Week', 'wpo-aom'),
				'last-week': __('Last Week', 'wpo-aom'),
				'current-month': __('Current Month', 'wpo-aom'),
				'next-month': __('Next Month', 'wpo-aom'),
				'last-month': __('Last Month', 'wpo-aom'),
			};
			const presetLabel =
				presetLabels[dateRangePreset] || __('Today', 'wpo-aom');
			return `${presetLabel}'s tasks - ${taskCountText}`;
		}

		if (dateRange.start) {
			const startDate = formatDate(dateRange.start);
			const endDate = dateRange.end
				? formatDate(dateRange.end)
				: startDate;

			if (startDate === endDate) {
				return `${startDate} - ${taskCountText}`;
			}
			return `${startDate} - ${endDate} (${taskCountText})`;
		}

		// Fallback
		return `${__('Tasks', 'wpo-aom')} - ${taskCountText}`;
	};

	const handleSort = (column: SortColumn) => {
		if (sortColumn === column) {
			// Toggle direction if same column
			setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
		} else {
			// New column, default to ascending
			setSortColumn(column);
			setSortDirection('asc');
		}
	};

	// Sort tasks based on current sort column and direction
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
					const aCompletedDateValue = getCompletedDate(a);
					const bCompletedDateValue = getCompletedDate(b);
					// Handle null dates (put them at the end)
					if (!aCompletedDateValue && !bCompletedDateValue) {
						return 0;
					}
					if (!aCompletedDateValue) {
						return 1;
					}
					if (!bCompletedDateValue) {
						return -1;
					}
					aValue = aCompletedDateValue.getTime();
					bValue = bCompletedDateValue.getTime();
					break;
				}

				default:
					return 0;
			}

			// Compare values
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
		<div className="calendar-task-list-container">
			<h3 className="calendar-task-list-title">{getTitle()}</h3>

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
							<th
								className="calendar-task-th-sortable"
								onClick={() => handleSort('dueDate')}
							>
								{__('Due date', 'wpo-aom')}{' '}
								{renderSortIcon('dueDate')}
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
						{sortedTasks.length === 0 ? (
							<tr>
								<td
									colSpan={6}
									className="calendar-task-empty-state"
								>
									{__(
										'No tasks found for the selected date range',
										'wpo-aom'
									)}
								</td>
							</tr>
						) : (
							sortedTasks.map((task) => (
								<TaskRow
									key={task.id}
									task={task}
									onTaskClick={onTaskClick}
								/>
							))
						)}
					</tbody>
				</table>
			</div>
		</div>
	);
};

export default TaskList;
