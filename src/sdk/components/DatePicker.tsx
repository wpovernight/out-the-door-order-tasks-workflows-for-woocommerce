import React, { useMemo, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useOnClickOutside } from '@sdk/hooks/useOnClickOutside';

interface DatePickerProps {
	id?: string;
	name?: string;
	value?: string; // YYYY-MM-DD
	placeholder?: string;
	onChange?: (date: string) => void;
}

type ViewMode = 'day' | 'month' | 'year';

interface DateParts {
	year: number;
	month: number;
	day: number;
}

const DAYS_OF_WEEK = [
	{
		short: __('Mo', 'advanced-order-manager'),
		full: __('Monday', 'advanced-order-manager'),
	},
	{
		short: __('Tu', 'advanced-order-manager'),
		full: __('Tuesday', 'advanced-order-manager'),
	},
	{
		short: __('We', 'advanced-order-manager'),
		full: __('Wednesday', 'advanced-order-manager'),
	},
	{
		short: __('Th', 'advanced-order-manager'),
		full: __('Thursday', 'advanced-order-manager'),
	},
	{
		short: __('Fr', 'advanced-order-manager'),
		full: __('Friday', 'advanced-order-manager'),
	},
	{
		short: __('Sa', 'advanced-order-manager'),
		full: __('Saturday', 'advanced-order-manager'),
	},
	{
		short: __('Su', 'advanced-order-manager'),
		full: __('Sunday', 'advanced-order-manager'),
	},
];

const MONTH_LABELS = [
	__('Jan', 'advanced-order-manager'),
	__('Feb', 'advanced-order-manager'),
	__('Mar', 'advanced-order-manager'),
	__('Apr', 'advanced-order-manager'),
	__('May', 'advanced-order-manager'),
	__('Jun', 'advanced-order-manager'),
	__('Jul', 'advanced-order-manager'),
	__('Aug', 'advanced-order-manager'),
	__('Sep', 'advanced-order-manager'),
	__('Oct', 'advanced-order-manager'),
	__('Nov', 'advanced-order-manager'),
	__('Dec', 'advanced-order-manager'),
];

function chunk<T>(items: T[], size: number): T[][] {
	const rows: T[][] = [];

	for (let index = 0; index < items.length; index += size) {
		rows.push(items.slice(index, index + size));
	}

	return rows;
}

function getDaysInMonth(year: number, month: number): number {
	return new Date(year, month + 1, 0).getDate();
}

function getFirstDayOfMonth(year: number, month: number): number {
	const day = new Date(year, month, 1).getDay(); // 0=Sun

	// ToDo: The first day of week should be configurable, currently hardcoded to Monday.
	return (day + 6) % 7; // shift so Monday=0
}

function formatDate(year: number, month: number, day: number): string {
	const monthString = String(month + 1).padStart(2, '0');
	const dayString = String(day).padStart(2, '0');

	return `${year}-${monthString}-${dayString}`;
}

function parseDate(dateString: string): DateParts | null {
	const match = dateString.match(/^(\d{4})-(\d{2})-(\d{2})$/);
	if (!match) {
		return null;
	}

	return {
		year: parseInt(match[1], 10),
		month: parseInt(match[2], 10) - 1,
		day: parseInt(match[3], 10),
	};
}

function datesEqual(a: DateParts, b: DateParts): boolean {
	return a.year === b.year && a.month === b.month && a.day === b.day;
}

function formatDisplayDate(parts: DateParts): string {
	const date = new Date(parts.year, parts.month, parts.day);

	return date.toLocaleDateString(undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
	});
}

interface CalendarDay {
	day: number;
	month: number; // actual month index (0-11)
	year: number;
	isAdjacent: boolean;
}

function getCalendarDays(year: number, month: number): CalendarDay[][] {
	const days: CalendarDay[] = [];
	const firstDay = getFirstDayOfMonth(year, month);
	const daysInMonth = getDaysInMonth(year, month);

	// Previous month fill
	if (firstDay > 0) {
		const previousMonth = month === 0 ? 11 : month - 1;
		const previousYear = month === 0 ? year - 1 : year;
		const previousDaysInMonth = getDaysInMonth(previousYear, previousMonth);

		for (let index = firstDay - 1; index >= 0; index--) {
			days.push({
				day: previousDaysInMonth - index,
				month: previousMonth,
				year: previousYear,
				isAdjacent: true,
			});
		}
	}

	// Current month
	for (let day = 1; day <= daysInMonth; day++) {
		days.push({ day, month, year, isAdjacent: false });
	}

	// Next month fill (complete last row to 42 cells = 6 rows)
	const nextMonth = month === 11 ? 0 : month + 1;
	const nextYear = month === 11 ? year + 1 : year;
	const remaining = 42 - days.length;

	for (let day = 1; day <= remaining; day++) {
		days.push({
			day,
			month: nextMonth,
			year: nextYear,
			isAdjacent: true,
		});
	}

	return chunk(days, 7);
}

interface CalendarMonth {
	month: number;
	year: number;
	isAdjacent: boolean;
}

function getCalendarMonths(year: number): CalendarMonth[][] {
	const months: CalendarMonth[] = [];

	for (let month = 0; month < 12; month++) {
		months.push({ month, year, isAdjacent: false });
	}

	// Next-year spill-over (4 cells) to fill a 4x4 grid.
	for (let month = 0; month < 4; month++) {
		months.push({ month, year: year + 1, isAdjacent: true });
	}

	return chunk(months, 4);
}

interface CalendarYear {
	year: number;
	isAdjacent: boolean;
}

function getCalendarYears(year: number): CalendarYear[][] {
	const decadeStart = Math.floor(year / 10) * 10;
	const years: CalendarYear[] = [];

	// Show 2 years before decade, the decade, and 4 years after = 16 cells (4x4).
	for (
		let currentYear = decadeStart - 2;
		currentYear < decadeStart + 14;
		currentYear++
	) {
		years.push({
			year: currentYear,
			isAdjacent:
				currentYear < decadeStart || currentYear >= decadeStart + 10,
		});
	}

	return chunk(years, 4);
}

export const DatePicker: React.FC<DatePickerProps> = ({
	id,
	name,
	value,
	placeholder,
	onChange,
}) => {
	const containerRef = useRef<HTMLDivElement>(null);
	const [open, setOpen] = useState(false);
	const [selectedDate, setSelectedDate] = useState<DateParts | null>(() =>
		value ? parseDate(value) : null
	);
	const [viewMode, setViewMode] = useState<ViewMode>('day');

	const today = useMemo(() => {
		const now = new Date();
		return {
			year: now.getFullYear(),
			month: now.getMonth(),
			day: now.getDate(),
		};
	}, []);

	const [viewYear, setViewYear] = useState(
		() => selectedDate?.year ?? today.year
	);
	const [viewMonth, setViewMonth] = useState(
		() => selectedDate?.month ?? today.month
	);

	const closePicker = () => setOpen(false);

	const openPicker = () => {
		setViewYear(selectedDate?.year ?? today.year);
		setViewMonth(selectedDate?.month ?? today.month);
		setViewMode('day');
		setOpen(true);
	};

	const toggleOpen = () => {
		if (open) {
			closePicker();
		} else {
			openPicker();
		}
	};

	useOnClickOutside(containerRef, closePicker);

	const commitDate = (parts: DateParts) => {
		setSelectedDate(parts);
		onChange?.(formatDate(parts.year, parts.month, parts.day));
		closePicker();
	};

	const handleDayClick = (calendarDay: CalendarDay) => {
		commitDate({
			year: calendarDay.year,
			month: calendarDay.month,
			day: calendarDay.day,
		});
	};

	const handleMonthClick = (calendarMonth: CalendarMonth) => {
		setViewYear(calendarMonth.year);
		setViewMonth(calendarMonth.month);
		setViewMode('day');
	};

	const handleYearClick = (calendarYear: CalendarYear) => {
		setViewYear(calendarYear.year);
		setViewMode('month');
	};

	const handleTitleClick = () => {
		if (viewMode === 'day') {
			setViewMode('month');
		} else if (viewMode === 'month') {
			setViewMode('year');
		}
	};

	const handleToday = () => {
		commitDate(today);
	};

	const handleClear = () => {
		setSelectedDate(null);
		onChange?.('');
		closePicker();
	};

	const goPrevious = () => {
		if (viewMode === 'day') {
			if (viewMonth === 0) {
				setViewMonth(11);
				setViewYear((year) => year - 1);
			} else {
				setViewMonth((month) => month - 1);
			}
		} else if (viewMode === 'month') {
			setViewYear((year) => year - 1);
		} else {
			setViewYear((year) => year - 10);
		}
	};

	const goNext = () => {
		if (viewMode === 'day') {
			if (viewMonth === 11) {
				setViewMonth(0);
				setViewYear((year) => year + 1);
			} else {
				setViewMonth((month) => month + 1);
			}
		} else if (viewMode === 'month') {
			setViewYear((year) => year + 1);
		} else {
			setViewYear((year) => year + 10);
		}
	};

	const calendarDays = useMemo(
		() => getCalendarDays(viewYear, viewMonth),
		[viewYear, viewMonth]
	);

	const calendarMonths = useMemo(
		() => getCalendarMonths(viewYear),
		[viewYear]
	);

	const calendarYears = useMemo(() => getCalendarYears(viewYear), [viewYear]);

	const headerLabel = useMemo(() => {
		if (viewMode === 'day') {
			return new Date(viewYear, viewMonth).toLocaleDateString(undefined, {
				month: 'long',
				year: 'numeric',
			});
		}

		if (viewMode === 'month') {
			return String(viewYear);
		}

		// View is decade.
		const decadeStart = Math.floor(viewYear / 10) * 10;

		return `${decadeStart} - ${decadeStart + 9}`;
	}, [viewMode, viewYear, viewMonth]);

	const selectedDateString = selectedDate
		? formatDate(selectedDate.year, selectedDate.month, selectedDate.day)
		: '';

	return (
		<div ref={containerRef} className="wpo-aom-datepicker-container">
			{name && (
				<input type="hidden" name={name} value={selectedDateString} />
			)}
			<button
				type="button"
				id={id}
				className={[
					'wpo-aom-datepicker-trigger',
					open && 'open',
					!selectedDate && 'wpo-aom-placeholder',
				]
					.filter(Boolean)
					.join(' ')}
				onClick={toggleOpen}
			>
				{selectedDate
					? formatDisplayDate(selectedDate)
					: placeholder ||
						__('Date picker', 'advanced-order-manager')}
			</button>
			{selectedDate && (
				<button
					type="button"
					className="wpo-button wpo-button-icon wpo-aom-datepicker-clear"
					onClick={handleClear}
					title={__('Clear date', 'advanced-order-manager')}
				>
					<span className="screen-reader-text">
						{__('Clear date', 'advanced-order-manager')}
					</span>
				</button>
			)}
			{open && (
				<div className="wpo-aom-datepicker-dropdown">
					<div className="wpo-aom-datepicker-header">
						<button
							type="button"
							className="wpo-button wpo-button-icon wpo-aom-datepicker-nav prev"
							onClick={goPrevious}
							title={__('Previous', 'advanced-order-manager')}
						>
							<span className="screen-reader-text">
								{__('Previous', 'advanced-order-manager')}
							</span>
						</button>
						<button
							type="button"
							className="wpo-aom-datepicker-title"
							onClick={handleTitleClick}
							disabled={viewMode === 'year'}
						>
							{headerLabel}
						</button>
						<button
							type="button"
							className="wpo-button wpo-button-icon wpo-aom-datepicker-nav next"
							onClick={goNext}
							title={__('Next', 'advanced-order-manager')}
						>
							<span className="screen-reader-text">
								{__('Next', 'advanced-order-manager')}
							</span>
						</button>
					</div>

					{viewMode === 'day' && (
						<table className="wpo-aom-datepicker-calendar days">
							<caption className="screen-reader-text">
								{headerLabel}
							</caption>
							<thead>
								<tr>
									{DAYS_OF_WEEK.map((day) => (
										<th key={day.short} scope="col">
											<abbr title={day.full}>
												{day.short}
											</abbr>
										</th>
									))}
								</tr>
							</thead>
							<tbody>
								{calendarDays.map((row, rowIndex) => (
									<tr key={rowIndex}>
										{row.map((calendarDay, columnIndex) => {
											const isSelected =
												selectedDate &&
												datesEqual(
													selectedDate,
													calendarDay
												);
											const isToday = datesEqual(
												today,
												calendarDay
											);

											return (
												<td
													key={columnIndex}
													role="button"
													tabIndex={0}
													className={[
														calendarDay.isAdjacent &&
															'adjacent',
														isSelected &&
															'selected',
														isToday && 'current',
													]
														.filter(Boolean)
														.join(' ')}
													onClick={() =>
														handleDayClick(
															calendarDay
														)
													}
												>
													{calendarDay.day}
												</td>
											);
										})}
									</tr>
								))}
							</tbody>
						</table>
					)}

					{viewMode === 'month' && (
						<table className="wpo-aom-datepicker-calendar months">
							<caption className="screen-reader-text">
								{headerLabel}
							</caption>
							<tbody>
								{calendarMonths.map((row, rowIndex) => (
									<tr key={rowIndex}>
										{row.map(
											(calendarMonth, columnIndex) => {
												const isSelected =
													selectedDate &&
													selectedDate.year ===
														calendarMonth.year &&
													selectedDate.month ===
														calendarMonth.month;

												const isCurrentMonth =
													today.year ===
														calendarMonth.year &&
													today.month ===
														calendarMonth.month;

												return (
													<td
														key={columnIndex}
														role="button"
														tabIndex={0}
														className={[
															calendarMonth.isAdjacent &&
																'adjacent',
															isSelected &&
																'selected',
															isCurrentMonth &&
																'current',
														]
															.filter(Boolean)
															.join(' ')}
														onClick={() =>
															handleMonthClick(
																calendarMonth
															)
														}
													>
														{
															MONTH_LABELS[
																calendarMonth
																	.month
															]
														}
													</td>
												);
											}
										)}
									</tr>
								))}
							</tbody>
						</table>
					)}

					{viewMode === 'year' && (
						<table className="wpo-aom-datepicker-calendar years">
							<caption className="screen-reader-text">
								{headerLabel}
							</caption>
							<tbody>
								{calendarYears.map((row, rowIndex) => (
									<tr key={rowIndex}>
										{row.map(
											(calendarYear, columnIndex) => {
												const isSelected =
													selectedDate &&
													selectedDate.year ===
														calendarYear.year;

												const isCurrentYear =
													today.year ===
													calendarYear.year;

												return (
													<td
														key={columnIndex}
														role="button"
														tabIndex={0}
														className={[
															calendarYear.isAdjacent &&
																'adjacent',
															isSelected &&
																'selected',
															isCurrentYear &&
																'current',
														]
															.filter(Boolean)
															.join(' ')}
														onClick={() =>
															handleYearClick(
																calendarYear
															)
														}
													>
														{calendarYear.year}
													</td>
												);
											}
										)}
									</tr>
								))}
							</tbody>
						</table>
					)}

					<ul className="wpo-aom-datepicker-actions">
						<li>
							<button
								type="button"
								className="wpo-button cancel"
								onClick={closePicker}
							>
								{__('Cancel', 'advanced-order-manager')}
							</button>
						</li>
						<li>
							<button
								type="button"
								className="wpo-button wpo-button-primary today"
								onClick={handleToday}
							>
								{__('Today', 'advanced-order-manager')}
							</button>
						</li>
					</ul>
				</div>
			)}
		</div>
	);
};
