import React, { useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { Task } from '@shared/types/task';
import TaskDayGroup from './TaskDayGroup';
import { getTaskDateField } from '@shared/utils/fieldUtils';
import { formatDate, DateRange, DateRangePreset } from '../utils';

interface TaskListProps {
	tasks: Task[];
	dateRange: DateRange;
	dateRangePreset: DateRangePreset;
	onTaskClick?: (task: Task) => void;
}

const getDateKey = (date: Date | null): string => {
	if (!date) {
		return 'no-date';
	}
	const y = date.getFullYear();
	const m = String(date.getMonth() + 1).padStart(2, '0');
	const d = String(date.getDate()).padStart(2, '0');
	return `${y}-${m}-${d}`;
};

const TaskList: React.FC<TaskListProps> = ({
	tasks,
	dateRange,
	dateRangePreset,
	onTaskClick,
}) => {
	// Generate dynamic title based on preset or date range
	const getTitle = (): React.ReactNode => {
		const taskCount = tasks.length;
		const taskCountBadge = (
			<span className="wpo-count-badge">{taskCount}</span>
		);

		if (dateRangePreset !== 'custom') {
			const presetLabels: Record<string, string> = {
				today: __('Today', 'wpo-aom'),
				tomorrow: __( 'Tomorrow', 'wpo-aom'),
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
			return (
				<>
					{presetLabel}'s tasks {taskCountBadge}
				</>
			);
		}

		if (dateRange.start) {
			const startDate = formatDate(dateRange.start);
			const endDate = dateRange.end
				? formatDate(dateRange.end)
				: startDate;

			if (startDate === endDate) {
				return (
					<>
						{startDate} {taskCountBadge}
					</>
				);
			}
			return (
				<>
					{startDate} - {endDate} {taskCountBadge}
				</>
			);
		}

		// Fallback
		return (
			<>
				{__('Tasks', 'wpo-aom')} - {taskCountBadge}
			</>
		);
	};

	// Group tasks by due date day; groups are ordered chronologically, no-date last.
	const groupedTasks = useMemo(() => {
		const groups = new Map<string, { date: Date | null; tasks: Task[] }>();

		for (const task of tasks) {
			const dueDate = getTaskDateField(task, 'due_date');
			const key = getDateKey(dueDate);

			if (!groups.has(key)) {
				groups.set(key, { date: dueDate, tasks: [] });
			}
			groups.get(key)!.tasks.push(task);
		}

		return Array.from(groups.entries())
			.sort(([keyA], [keyB]) => {
				if (keyA === 'no-date') {
					return 1;
				}
				if (keyB === 'no-date') {
					return -1;
				}
				return keyA.localeCompare(keyB);
			})
			.map(([, group]) => group);
	}, [tasks]);

	return (
		<div className="calendar-task-list-container">
			<h4 className="calendar-task-list-title">{getTitle()}</h4>

			{groupedTasks.length === 0 ? (
				<div className="calendar-task-empty-state">
					{__(
						'No tasks found for the selected date range',
						'wpo-aom'
					)}
				</div>
			) : (
				groupedTasks.map((group) => (
					<TaskDayGroup
						key={group.date ? getDateKey(group.date) : 'no-date'}
						date={group.date}
						tasks={group.tasks}
						onTaskClick={onTaskClick}
					/>
				))
			)}
		</div>
	);
};

export default TaskList;
