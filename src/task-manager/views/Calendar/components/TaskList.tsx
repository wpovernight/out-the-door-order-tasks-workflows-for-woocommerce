import React, { useMemo } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Task, getTaskDateField } from '@sdk';
import TaskDayGroup from './TaskDayGroup';
import {
	formatDate,
	formatDateRange,
	isSameDate,
	DateRange,
	DateRangePreset,
} from '../utils';

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
				today: __('Today', 'advanced-order-manager-for-woocommerce'),
				tomorrow: __('Tomorrow', 'advanced-order-manager-for-woocommerce'),
				yesterday: __('Yesterday', 'advanced-order-manager-for-woocommerce'),
				'current-week': __('Current Week', 'advanced-order-manager-for-woocommerce'),
				'next-week': __('Next Week', 'advanced-order-manager-for-woocommerce'),
				'last-week': __('Last Week', 'advanced-order-manager-for-woocommerce'),
				'current-month': __('Current Month', 'advanced-order-manager-for-woocommerce'),
				'next-month': __('Next Month', 'advanced-order-manager-for-woocommerce'),
				'last-month': __('Last Month', 'advanced-order-manager-for-woocommerce'),
			};
			const presetLabel =
				presetLabels[dateRangePreset] ||
				__('Today', 'advanced-order-manager-for-woocommerce');
			return (
				<>
					{sprintf(
						/* translators: %s: date range label (e.g. "Today", "Current Week") */
						__("%s's tasks", 'advanced-order-manager-for-woocommerce'),
						presetLabel
					)}{' '}
					{taskCountBadge}
				</>
			);
		}

		if (dateRange.start) {
			if (dateRange.end && !isSameDate(dateRange.start, dateRange.end)) {
				return (
					<>
						{formatDateRange(dateRange.start, dateRange.end)}{' '}
						{taskCountBadge}
					</>
				);
			}
			return (
				<>
					{formatDate(dateRange.start)} {taskCountBadge}
				</>
			);
		}

		// Fallback
		return (
			<>
				{__('Tasks', 'advanced-order-manager-for-woocommerce')} - {taskCountBadge}
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
						'advanced-order-manager-for-woocommerce'
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
