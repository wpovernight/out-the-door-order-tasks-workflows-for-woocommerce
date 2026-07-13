import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { DateRange } from '../context/ViewTaskContext';
import { CalendarDay } from '../data';
import CalendarGrid from './CalendarGrid';

interface DateRangeSelectorProps {
	pendingDateRange: DateRange;
	calendarDays: CalendarDay[];
	onDateClick: (date: Date) => void;
	onDateInputChange: (date: Date | null, field: 'start' | 'end') => void;
	onPreviousMonth: () => void;
	onNextMonth: () => void;
	onCancel: () => void;
	onApply: () => void;
	monthName: string;
}

const formatDateForInput = (date: Date | null): string => {
	if (!date) {
		return '';
	}
	const day = String(date.getDate()).padStart(2, '0');
	const month = String(date.getMonth() + 1).padStart(2, '0');
	const year = date.getFullYear();
	return `${day}/${month}/${year}`;
};

const isSameDate = (a: Date | null, b: Date | null): boolean => {
	if (!a || !b) {
		return false;
	}
	return (
		a.getFullYear() === b.getFullYear() &&
		a.getMonth() === b.getMonth() &&
		a.getDate() === b.getDate()
	);
};

const isValidDateString = (value: string): boolean => {
	if (!/^\d{2}\/\d{2}\/\d{4}$/.test(value)) {
		return false;
	}
	const [day, month, year] = value.split('/').map(Number);
	const date = new Date(year, month - 1, day);
	return (
		date.getFullYear() === year &&
		date.getMonth() === month - 1 &&
		date.getDate() === day
	);
};

const parseDateInput = (value: string): Date => {
	const [day, month, year] = value.split('/').map(Number);
	return new Date(year, month - 1, day);
};

const DateRangeSelector: React.FC<DateRangeSelectorProps> = ({
	pendingDateRange,
	calendarDays,
	onDateClick,
	onDateInputChange,
	onPreviousMonth,
	onNextMonth,
	onCancel,
	onApply,
	monthName,
}) => {
	const [startText, setStartText] = useState(
		formatDateForInput(pendingDateRange.start)
	);
	const [endText, setEndText] = useState(
		formatDateForInput(pendingDateRange.end)
	);

	const isRange =
		pendingDateRange.start &&
		pendingDateRange.end &&
		!isSameDate(pendingDateRange.start, pendingDateRange.end);

	// Sync local text when pendingDateRange changes externally (e.g. calendar click)
	useEffect(() => {
		setStartText(formatDateForInput(pendingDateRange.start));
	}, [pendingDateRange.start]);

	useEffect(() => {
		setEndText(formatDateForInput(pendingDateRange.end));
	}, [pendingDateRange.end]);

	const handleStartDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
		const value = e.target.value;
		setStartText(value);
		if (!value) {
			onDateInputChange(null, 'start');
		} else if (isValidDateString(value)) {
			onDateInputChange(parseDateInput(value), 'start');
		}
	};

	const handleEndDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
		const value = e.target.value;
		setEndText(value);
		if (!value) {
			onDateInputChange(null, 'end');
		} else if (isValidDateString(value)) {
			onDateInputChange(parseDateInput(value), 'end');
		}
	};

	return (
		<div className="calendar-selector">
			<div className="calendar-navigation">
				<span>{monthName}</span>
				<button
					className="wpo-button wpo-navigate-previous"
					onClick={onPreviousMonth}
				/>
				<button
					className="wpo-button wpo-navigate-next"
					onClick={onNextMonth}
				/>
			</div>

			<div className="date-range-inputs">
				{isRange ? (
					<>
						<label
							htmlFor="date-input-start"
							className="screen-reader-text"
						>
							{__('From', 'advanced-order-manager')}
						</label>
						<input
							id="date-input-start"
							type="text"
							pattern="\d{2}/\d{2}/\d{4}"
							className="date-input date-input-start"
							value={startText}
							onChange={handleStartDateChange}
						/>
						<span className="date-separator">-</span>
						<label
							htmlFor="date-input-end"
							className="screen-reader-text"
						>
							{__('To', 'advanced-order-manager')}
						</label>
						<input
							id="date-input-end"
							type="text"
							pattern="\d{2}/\d{2}/\d{4}"
							className="date-input date-input-end"
							value={endText}
							onChange={handleEndDateChange}
						/>
					</>
				) : (
					<>
						<label
							htmlFor="date-input-single"
							className="screen-reader-text"
						>
							{__('Select Date', 'advanced-order-manager')}
						</label>
						<input
							id="date-input-single"
							type="text"
							pattern="\d{2}/\d{2}/\d{4}"
							className="date-input date-input-single"
							value={startText}
							onChange={handleStartDateChange}
						/>
					</>
				)}
			</div>

			<CalendarGrid days={calendarDays} onDayClick={onDateClick} />

			<div className="calendar-actions">
				<button
					className="wpo-button wpo-clear-button"
					onClick={onCancel}
				>
					{__('Clear', 'advanced-order-manager')}
				</button>
				<button
					className="wpo-button wpo-apply-button"
					onClick={onApply}
				>
					{__('Apply', 'advanced-order-manager')}
				</button>
			</div>
		</div>
	);
};

export default DateRangeSelector;
