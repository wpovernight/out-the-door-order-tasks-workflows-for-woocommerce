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
	const year = date.getFullYear();
	const month = String(date.getMonth() + 1).padStart(2, '0');
	const day = String(date.getDate()).padStart(2, '0');
	return `${year}-${month}-${day}`;
};

const isValidDateString = (value: string): boolean => {
	if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
		return false;
	}
	const [year, month, day] = value.split('-').map(Number);
	const date = new Date(year, month - 1, day);
	return (
		date.getFullYear() === year &&
		date.getMonth() === month - 1 &&
		date.getDate() === day
	);
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
	const [startText, setStartText] = useState(formatDateForInput(pendingDateRange.start));
	const [endText, setEndText] = useState(formatDateForInput(pendingDateRange.end));

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
			const [year, month, day] = value.split('-').map(Number);
			onDateInputChange(new Date(year, month - 1, day), 'start');
		}
	};

	const handleEndDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
		const value = e.target.value;
		setEndText(value);
		if (!value) {
			onDateInputChange(null, 'end');
		} else if (isValidDateString(value)) {
			const [year, month, day] = value.split('-').map(Number);
			onDateInputChange(new Date(year, month - 1, day), 'end');
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
				<label htmlFor="date-input-start" className="screenReader">
					{__('From', 'wpo-aom')}
				</label>
				<input
					id="date-input-start"
					type="text"
                    pattern="\d{4}-\d{2}-\d{2}"
					className="date-input date-input-start"
					value={startText}
					onChange={handleStartDateChange}
				/>
				<span className="date-separator">-</span>
				<label htmlFor="date-input-end" className="screenReader">
					{__('To', 'wpo-aom')}
				</label>
				<input
					id="date-input-end"
                    type="text"
                    pattern="\d{4}-\d{2}-\d{2}"
                    className="date-input date-input-end"
					value={endText}
					onChange={handleEndDateChange}
				/>
			</div>

			<CalendarGrid days={calendarDays} onDayClick={onDateClick} />

			<div className="calendar-actions">
				<button
					className="wpo-button wpo-clear-button"
					onClick={onCancel}
				>
					{__('Clear', 'wpo-aom')}
				</button>
				<button
					className="wpo-button wpo-apply-button"
					onClick={onApply}
				>
					{__('Apply', 'wpo-aom')}
				</button>
			</div>
		</div>
	);
};

export default DateRangeSelector;
