import React, { useContext, useState, useMemo } from 'react';
import { Task, useTasks, getTaskDateField, isTaskArchived } from '@sdk';
import { getDateRangeFromPreset, DateRange, DateRangePreset } from '../utils';
import { useView } from '@taskManager/context/ViewContext';

export type { DateRangePreset, DateRange };

interface ViewTaskContextType {
	// Tasks
	filteredTasks: Task[];

	// Date state
	currentDate: Date;
	setCurrentDate: (date: Date) => void;
	dateRange: DateRange;
	setDateRange: (range: DateRange) => void;
	pendingDateRange: DateRange;
	setPendingDateRange: (range: DateRange) => void;
	applyDateRange: () => void;

	// Date range preset
	dateRangePreset: DateRangePreset;
	setDateRangePreset: (preset: DateRangePreset) => void;
	appliedDateRangePreset: DateRangePreset;

	// Selected task
	selectedTask: Task | null;
	selectTask: (task: Task) => void;
	clearSelectedTask: () => void;

	// Navigation
	goToPreviousMonth: () => void;
	goToNextMonth: () => void;
}

const ViewTaskContext = React.createContext<ViewTaskContextType | undefined>(
	undefined
);

export const ViewTaskProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const { tasks } = useTasks();
	const { searchQuery } = useView();

	// Date state
	const [currentDate, setCurrentDate] = useState(new Date());
	const today = new Date();
	today.setHours(0, 0, 0, 0);

	// Date range preset (pending - not yet applied)
	const [dateRangePreset, setDateRangePresetState] =
		useState<DateRangePreset>('today');

	// Applied date range preset (used for display in title)
	const [appliedDateRangePreset, setAppliedDateRangePreset] =
		useState<DateRangePreset>('today');

	// Applied date range (used for filtering tasks)
	const [dateRange, setDateRange] = useState<DateRange>({
		start: today,
		end: null,
	});

	// Pending date range (temporary selection before Apply is clicked)
	const [pendingDateRange, setPendingDateRange] = useState<DateRange>({
		start: today,
		end: null,
	});

	const [selectedTask, setSelectedTask] = useState<Task | null>(null);

	const setDateRangePreset = (preset: DateRangePreset) => {
		setDateRangePresetState(preset);
		if (preset !== 'custom') {
			const range = getDateRangeFromPreset(preset);
			setPendingDateRange(range);
			if (range.start) {
				setCurrentDate(
					new Date(
						range.start.getFullYear(),
						range.start.getMonth(),
						1
					)
				);
			}
		}
	};

	const applyDateRange = () => {
		setDateRange(pendingDateRange);
		setAppliedDateRangePreset(dateRangePreset);
	};

	const filteredTasks = useMemo(() => {
		// If no date range is set, return empty array (no tasks to show)
		if (!dateRange.start || tasks.length === 0) {
			return [];
		}

		const endDate = dateRange.end || dateRange.start;
		const startOfDay = new Date(dateRange.start);
		startOfDay.setHours(0, 0, 0, 0);

		const endOfDay = new Date(endDate);
		endOfDay.setHours(23, 59, 59, 999);

		const query = searchQuery.toLowerCase();
		return tasks.filter((task) => {
			if (isTaskArchived(task)) {
				return false;
			}

			const taskDueDate = getTaskDateField(task, 'due_date');
			if (!taskDueDate) {
				return false;
			}

			if (taskDueDate < startOfDay || taskDueDate > endOfDay) {
				return false;
			}

			if (!query) {
				return true;
			}
			return (
				task.title.toLowerCase().includes(query) ||
				(task.description ?? '').toLowerCase().includes(query)
			);
		});
	}, [tasks, dateRange, searchQuery]);

	const selectTask = (task: Task) => {
		setSelectedTask(task);
	};

	const clearSelectedTask = () => {
		setSelectedTask(null);
	};

	const goToPreviousMonth = () => {
		setCurrentDate(
			new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1)
		);
	};

	const goToNextMonth = () => {
		setCurrentDate(
			new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1)
		);
	};

	return (
		<ViewTaskContext.Provider
			value={{
				filteredTasks,
				currentDate,
				setCurrentDate,
				dateRange,
				setDateRange,
				pendingDateRange,
				setPendingDateRange,
				applyDateRange,
				dateRangePreset,
				setDateRangePreset,
				appliedDateRangePreset,
				selectedTask,
				selectTask,
				clearSelectedTask,
				goToPreviousMonth,
				goToNextMonth,
			}}
		>
			{children}
		</ViewTaskContext.Provider>
	);
};

export const useViewTasks = (): ViewTaskContextType => {
	const context = useContext(ViewTaskContext);
	if (!context) {
		throw new Error('useViewTasks must be used within a ViewTaskProvider');
	}
	return context;
};
