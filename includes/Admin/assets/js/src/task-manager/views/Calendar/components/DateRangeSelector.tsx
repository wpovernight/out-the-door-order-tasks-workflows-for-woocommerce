import React from 'react';
import { __ } from '@wordpress/i18n';
import { DateRange } from '../context/ViewTaskContext';
import { CalendarDay } from '../data';
import CalendarGrid from './CalendarGrid';

interface DateRangeSelectorProps {
	currentDate: Date;
	dateRange: DateRange;
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

const DateRangeSelector: React.FC<DateRangeSelectorProps> = ({
	currentDate,
	dateRange,
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
	// Format date for input field (YYYY-MM-DD)
	const formatDateForInput = (date: Date | null): string => {
		if (!date) {
			return '';
		}
		const year = date.getFullYear();
		const month = String(date.getMonth() + 1).padStart(2, '0');
		const day = String(date.getDate()).padStart(2, '0');
		return `${year}-${month}-${day}`;
	};

	const handleStartDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
		const value = e.target.value;
		if (value) {
			const date = new Date(value);
			date.setHours(0, 0, 0, 0);
			onDateInputChange(date, 'start');
		} else {
			onDateInputChange(null, 'start');
		}
	};

	const handleEndDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
		const value = e.target.value;
		if (value) {
			const date = new Date(value);
			date.setHours(0, 0, 0, 0);
			onDateInputChange(date, 'end');
		} else {
			onDateInputChange(null, 'end');
		}
	};

	return (
		<div className="calendar-selector">
			<div className="calendar-navigation">
				<h2>{monthName}</h2>
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
					From
				</label>
				<input
					id="date-input-start"
					type="date"
					className="date-input date-input-start"
					value={formatDateForInput(pendingDateRange.start)}
					onChange={handleStartDateChange}
				/>
				<span className="date-separator">-</span>
				<label htmlFor="date-input-end" className="screenReader">
					To
				</label>
				<input
					id="date-input-end"
					type="date"
					className="date-input date-input-end"
					value={formatDateForInput(pendingDateRange.end)}
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
