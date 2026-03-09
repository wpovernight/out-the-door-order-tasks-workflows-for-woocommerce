import React from 'react';
import { __ } from '@wordpress/i18n';
import { CalendarDay } from '../data';

interface CalendarGridProps {
	days: CalendarDay[];
	onDayClick: (date: Date) => void;
}

const WEEKDAYS = [
	{ abbr: __('Monday', 'wpo-aom'), short: __('Mo', 'wpo-aom') },
	{ abbr: __('Tuesday', 'wpo-aom'), short: __('Tu', 'wpo-aom') },
	{ abbr: __('Wednesday', 'wpo-aom'), short: __('We', 'wpo-aom') },
	{ abbr: __('Thursday', 'wpo-aom'), short: __('Th', 'wpo-aom') },
	{ abbr: __('Friday', 'wpo-aom'), short: __('Fr', 'wpo-aom') },
	{ abbr: __('Saturday', 'wpo-aom'), short: __('Sa', 'wpo-aom') },
	{ abbr: __('Sunday', 'wpo-aom'), short: __('Su', 'wpo-aom') },
];

const CalendarGrid: React.FC<CalendarGridProps> = ({ days, onDayClick }) => {
	const weeks: CalendarDay[][] = [];
	for (let i = 0; i < days.length; i += 7) {
		weeks.push(days.slice(i, i + 7));
	}

	return (
		<table className="calendar-grid" role="grid">
			<thead>
				<tr>
					{WEEKDAYS.map((day) => (
						<th key={day.short} abbr={day.abbr} scope="col">
							{day.short}
						</th>
					))}
				</tr>
			</thead>
			<tbody>
				{weeks.map((week, weekIndex) => (
					<tr key={weekIndex}>
						{week.map((calendarDay, dayIndex) => {
							const classNames = ['calendar-day'];

							if (!calendarDay.isCurrentMonth) {
								classNames.push('calendar-day-other-month');
							}
							if (calendarDay.isToday) {
								classNames.push('calendar-day-today');
							}
							if (calendarDay.isSelected) {
								classNames.push('calendar-day-selected');
							}
							if (calendarDay.isInRange) {
								classNames.push('calendar-day-in-range');
							}

							return (
								<td
									key={dayIndex}
									role="gridcell"
									aria-selected={calendarDay.isSelected}
								>
									<button
										className={classNames.join(' ')}
										onClick={() =>
											onDayClick(calendarDay.date)
										}
										disabled={!calendarDay.isCurrentMonth}
										tabIndex={
											calendarDay.isSelected ||
											(weekIndex === 0 && dayIndex === 0)
												? 0
												: -1
										}
									>
										{calendarDay.day}
									</button>
								</td>
							);
						})}
					</tr>
				))}
			</tbody>
		</table>
	);
};

export default CalendarGrid;
