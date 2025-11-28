import React, { useMemo } from 'react';
import { useViewTasks } from '../context/ViewTaskContext';
import { useTasks } from '@shared/context/TaskContext';
import { useSidebarModal } from '@shared/context/SidebarModalContext';
import { TaskForm } from '@shared/components/TaskForm';
import { useLocalized } from '@shared/hooks/useLocalized';
import { Task } from '@shared/types/task';
import { CalendarDay } from '../data';
import {
	generateCalendarDays,
	getMonthData,
	isDateInRange,
	isSameDate,
	isToday,
} from '../utils';
import DateRangePresetSelector from './DateRangePresetSelector';
import DateRangeSelector from './DateRangeSelector';
import TaskList from './TaskList';

export const CalendarContent: React.FC = () => {
	const {
		filteredTasks,
		currentDate,
		dateRange,
		pendingDateRange,
		setPendingDateRange,
		applyDateRange,
		dateRangePreset,
		setDateRangePreset,
		appliedDateRangePreset,
		selectTask,
		goToPreviousMonth,
		goToNextMonth,
	} = useViewTasks();

	const { deleteTask, setTasks } = useTasks();
	const { openSidebar, closeSidebar } = useSidebarModal();
	const localized = useLocalized();

	// Generate calendar data
	const { monthName } = useMemo(
		() => getMonthData(currentDate),
		[currentDate]
	);

	const calendarDays: CalendarDay[] = useMemo(() => {
		const days = generateCalendarDays(currentDate);
		const { month: currentMonth, year: currentYear } =
			getMonthData(currentDate);

		return days.map((date) => ({
			date,
			day: date.getDate(),
			isCurrentMonth:
				date.getMonth() === currentMonth &&
				date.getFullYear() === currentYear,
			isToday: isToday(date),
			isSelected:
				isSameDate(date, pendingDateRange.start) ||
				isSameDate(date, pendingDateRange.end),
			isInRange: isDateInRange(
				date,
				pendingDateRange.start,
				pendingDateRange.end
			),
		}));
	}, [currentDate, pendingDateRange]);

	// Handlers
	const handleDateClick = (date: Date) => {
		// Switch to custom mode when manually selecting dates
		if (dateRangePreset !== 'custom') {
			setDateRangePreset('custom');
		}

		if (!pendingDateRange.start || pendingDateRange.end) {
			// Start new selection
			setPendingDateRange({ start: date, end: null });
		} else {
			// Complete range selection
			if (date < pendingDateRange.start) {
				setPendingDateRange({
					start: date,
					end: pendingDateRange.start,
				});
			} else {
				setPendingDateRange({
					start: pendingDateRange.start,
					end: date,
				});
			}
		}
	};

	const handleDateInputChange = (
		date: Date | null,
		field: 'start' | 'end'
	) => {
		// Switch to custom mode when manually changing input dates
		if (dateRangePreset !== 'custom') {
			setDateRangePreset('custom');
		}

		if (field === 'start') {
			setPendingDateRange({ start: date, end: pendingDateRange.end });
		} else {
			setPendingDateRange({ start: pendingDateRange.start, end: date });
		}
	};

	const handleCancel = () => {
		setPendingDateRange({ start: null, end: null });
		setDateRangePreset('custom');
	};

	const handleApply = () => {
		applyDateRange();
	};

	const handleTaskEdit = (task: Task) => {
		openSidebar(
			<TaskForm
				task={task}
				onDone={closeSidebar}
				onTaskSaved={(updatedTask) => {
					setTasks((prevTasks) =>
						prevTasks.map((t) =>
							t.id === updatedTask.id ? updatedTask : t
						)
					);
					closeSidebar();
				}}
			/>,
			{
				title: `${localized.actions.edit}: ${task.title}`,
			}
		);
	};

	const handleTaskDelete = async (taskId: number) => {
		try {
			await deleteTask(taskId);
			setTasks((prevTasks) => prevTasks.filter((t) => t.id !== taskId));
		} catch (error) {
			console.error('Failed to delete task:', error);
		}
	};

	return (
		<div className="calendar-view-container">
			<div className="calendar-sidebar">
				<DateRangePresetSelector
					value={dateRangePreset}
					onChange={setDateRangePreset}
				/>

				<DateRangeSelector
					currentDate={currentDate}
					dateRange={dateRange}
					pendingDateRange={pendingDateRange}
					calendarDays={calendarDays}
					onDateClick={handleDateClick}
					onDateInputChange={handleDateInputChange}
					onPreviousMonth={goToPreviousMonth}
					onNextMonth={goToNextMonth}
					onCancel={handleCancel}
					onApply={handleApply}
					monthName={monthName}
				/>
			</div>

			<TaskList
				tasks={filteredTasks}
				dateRange={dateRange}
				dateRangePreset={appliedDateRangePreset}
				onTaskClick={selectTask}
				onTaskEdit={handleTaskEdit}
				onTaskDelete={handleTaskDelete}
			/>
		</div>
	);
};
