import React, { useState, useMemo } from 'react';
import { Task, isFieldOption } from '@shared/types/task';
import TaskRow from './TaskRow';
import { useLocalized } from '@shared/hooks/useLocalized';
import { getTaskDueDate, getFieldValue, formatDate, DateRange, DateRangePreset } from '../utils';

type SortColumn = 'title' | 'priority' | 'status' | 'dueDate';
type SortDirection = 'asc' | 'desc';

interface TaskListProps {
	tasks: Task[];
	dateRange: DateRange;
	dateRangePreset: DateRangePreset;
	onTaskClick?: (task: Task) => void;
	onTaskEdit?: (task: Task) => void;
	onTaskDelete?: (taskId: number) => void;
}

const TaskList: React.FC<TaskListProps> = ({
	tasks,
	dateRange,
	dateRangePreset,
	onTaskClick,
	onTaskEdit,
	onTaskDelete,
}) => {
	const localized = useLocalized();
	const [sortColumn, setSortColumn] = useState<SortColumn>('dueDate');
	const [sortDirection, setSortDirection] = useState<SortDirection>('asc');

	// Generate dynamic title based on preset or date range
	const getTitle = (): string => {
		const taskCount = tasks.length;
		const taskCountText =
			taskCount === 1
				? `1 ${localized.calendar.task.toLowerCase()}`
				: `${taskCount} ${localized.calendar.tasks}`;

		if (dateRangePreset !== 'custom') {
			const presetLabels: Record<string, string> = {
				today: localized.calendar.dateRangePresets.today,
				yesterday: localized.calendar.dateRangePresets.yesterday,
				'current-week': localized.calendar.dateRangePresets.currentWeek,
				'last-week': localized.calendar.dateRangePresets.lastWeek,
				'current-month': localized.calendar.dateRangePresets.currentMonth,
				'last-month': localized.calendar.dateRangePresets.lastMonth,
			};
			const presetLabel = presetLabels[dateRangePreset] || localized.calendar.dateRangePresets.today;
			return `${presetLabel}'s tasks - ${taskCountText}`;
		}

		if (dateRange.start) {
			const startDate = formatDate(dateRange.start);
			const endDate = dateRange.end ? formatDate(dateRange.end) : startDate;

			if (startDate === endDate) {
				return `${startDate} - ${taskCountText}`;
			}
			return `${startDate} - ${endDate} (${taskCountText})`;
		}

		// Fallback
		return `${localized.calendar.tasks} - ${taskCountText}`;
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
		const sorted = [...tasks].sort((a, b) => {
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

		return sorted;
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
			<h2>{getTitle()}</h2>

			<div className="calendar-task-table-wrapper">
				<table>
					<thead>
						<tr>
							<th
								className="calendar-task-title calendar-task-th-sortable"
								onClick={() => handleSort('title')}
							>
								{localized.calendar.task}{' '}
								{renderSortIcon('title')}
							</th>
							<th
								className="calendar-task-th-sortable"
								onClick={() => handleSort('priority')}
							>
								{localized.calendar.priority}{' '}
								{renderSortIcon('priority')}
							</th>
							<th
								className="calendar-task-th-sortable"
								onClick={() => handleSort('status')}
							>
								{localized.calendar.status}{' '}
								{renderSortIcon('status')}
							</th>
							<th
								className="calendar-task-th-sortable"
								onClick={() => handleSort('dueDate')}
							>
								{localized.calendar.dueDate}{' '}
								{renderSortIcon('dueDate')}
							</th>
							<th>{localized.calendar.description}</th>
							<th>{localized.actions.actions}</th>
						</tr>
					</thead>
					<tbody>
						{sortedTasks.length === 0 ? (
							<tr>
								<td
									colSpan={6}
									className="calendar-task-empty-state"
								>
									{localized.calendar.noTasksFound}
								</td>
							</tr>
						) : (
							sortedTasks.map((task) => (
								<TaskRow
									key={task.id}
									task={task}
									onTaskClick={onTaskClick}
									onTaskEdit={onTaskEdit}
									onTaskDelete={onTaskDelete}
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
