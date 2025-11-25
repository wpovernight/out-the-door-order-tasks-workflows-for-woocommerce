import { Task, FieldResolved } from '@shared/types/task';

export const getMonthData = (date: Date) => {
	const year = date.getFullYear();
	const month = date.getMonth();
	const monthName = date.toLocaleDateString('en-US', {
		month: 'long',
		year: 'numeric',
	});
	const daysInMonth = new Date(year, month + 1, 0).getDate();
	const firstDayOfMonth = new Date(year, month, 1).getDay();

	// Adjust for Monday start (0 = Monday, 6 = Sunday) - ToDo: Make configurable using settings
	const adjustedFirstDay = firstDayOfMonth === 0 ? 6 : firstDayOfMonth - 1;

	return {
		year,
		month,
		monthName,
		daysInMonth,
		firstDayOfMonth: adjustedFirstDay,
	};
};

export const generateCalendarDays = (currentDate: Date) => {
	const { year, month, daysInMonth, firstDayOfMonth } =
		getMonthData(currentDate);
	const days: Date[] = [];

	// Add days from previous month
	const prevMonth = new Date(year, month, 0);
	const prevMonthDays = prevMonth.getDate();
	for (let i = firstDayOfMonth - 1; i >= 0; i--) {
		days.push(new Date(year, month - 1, prevMonthDays - i));
	}

	// Add days of current month
	for (let i = 1; i <= daysInMonth; i++) {
		days.push(new Date(year, month, i));
	}

	// Add days from next month to complete 6 rows
	const remainingCells = 42 - days.length;
	for (let i = 1; i <= remainingCells; i++) {
		days.push(new Date(year, month + 1, i));
	}

	return days;
};

export const isDateInRange = (
	date: Date,
	start: Date | null,
	end: Date | null
): boolean => {
	if (!start) {
		return false;
	}

	const dateOnly = new Date(
		date.getFullYear(),
		date.getMonth(),
		date.getDate()
	);
	const startOnly = new Date(
		start.getFullYear(),
		start.getMonth(),
		start.getDate()
	);
	const endOnly = end
		? new Date(end.getFullYear(), end.getMonth(), end.getDate())
		: startOnly;

	return dateOnly >= startOnly && dateOnly <= endOnly;
};

export const isSameDate = (date1: Date, date2: Date | null): boolean => {
	if (!date2) {
		return false;
	}

	return (
		date1.getDate() === date2.getDate() &&
		date1.getMonth() === date2.getMonth() &&
		date1.getFullYear() === date2.getFullYear()
	);
};

export const isToday = (date: Date): boolean => {
	const today = new Date();
	return isSameDate(date, today);
};

export const formatDate = (date: Date): string => {
	return date.toLocaleDateString('en-US', {
		month: 'short',
		day: 'numeric',
		year: 'numeric',
	});
};

export interface DateRange {
	start: Date | null;
	end: Date | null;
}

export type DateRangePreset =
	| 'today'
	| 'yesterday'
	| 'current-week'
	| 'last-week'
	| 'current-month'
	| 'last-month'
	| 'custom';

export const getDateRangeFromPreset = (preset: DateRangePreset): DateRange => {
	const today = new Date();
	today.setHours(0, 0, 0, 0);

	switch (preset) {
		case 'today':
			return { start: new Date(today), end: null };

		case 'yesterday': {
			const yesterday = new Date(today);
			yesterday.setDate(yesterday.getDate() - 1);
			return { start: yesterday, end: null };
		}

		case 'current-week': {
			const startOfWeek = new Date(today);
			const day = startOfWeek.getDay();
			const diff = day === 0 ? -6 : 1 - day; // Monday as start of week - ToDo: Make configurable using settings
			startOfWeek.setDate(startOfWeek.getDate() + diff);

			const endOfWeek = new Date(startOfWeek);
			endOfWeek.setDate(endOfWeek.getDate() + 6);

			return { start: startOfWeek, end: endOfWeek };
		}

		case 'last-week': {
			const startOfLastWeek = new Date(today);
			const day = startOfLastWeek.getDay();
			const diff = day === 0 ? -6 : 1 - day;
			startOfLastWeek.setDate(startOfLastWeek.getDate() + diff - 7);

			const endOfLastWeek = new Date(startOfLastWeek);
			endOfLastWeek.setDate(endOfLastWeek.getDate() + 6);

			return { start: startOfLastWeek, end: endOfLastWeek };
		}

		case 'current-month': {
			const startOfMonth = new Date(
				today.getFullYear(),
				today.getMonth(),
				1
			);
			const endOfMonth = new Date(
				today.getFullYear(),
				today.getMonth() + 1,
				0
			);

			return { start: startOfMonth, end: endOfMonth };
		}

		case 'last-month': {
			const startOfLastMonth = new Date(
				today.getFullYear(),
				today.getMonth() - 1,
				1
			);
			const endOfLastMonth = new Date(
				today.getFullYear(),
				today.getMonth(),
				0
			);

			return { start: startOfLastMonth, end: endOfLastMonth };
		}

		case 'custom':
		default:
			return { start: null, end: null };
	}
};

// Field utility functions
export const getFieldBySlug = (task: Task, slug: string) => {
	return task.fields.find((field) => field.slug === slug);
};

export const getFieldValue = (
	task: Task,
	slug: string
): FieldResolved | null => {
	const field = getFieldBySlug(task, slug);
	if (!field || !field.values || field.values.length === 0) {
		return null;
	}

	return field.values[0].resolved;
};

export const getFieldRawValue = (
	task: Task,
	slug: string
): string | number | boolean | null => {
	const field = getFieldBySlug(task, slug);
	if (!field || !field.values || field.values.length === 0) {
		return null;
	}

	const rawValue = field.values[0].raw;
	return Array.isArray(rawValue) ? rawValue[0] : rawValue;
};

export const getTaskDueDate = (task: Task): Date | null => {
	const dueDateValue = getFieldRawValue(task, 'due-date');
	if (!dueDateValue || typeof dueDateValue !== 'string') {
		return null;
	}

	const date = new Date(dueDateValue);
	return isNaN(date.getTime()) ? null : date;
};
