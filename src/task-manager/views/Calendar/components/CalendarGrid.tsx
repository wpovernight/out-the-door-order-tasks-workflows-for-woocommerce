import React from 'react';
import { __ } from '@wordpress/i18n';
import { CalendarDay } from '../data';

interface CalendarGridProps {
	days: CalendarDay[];
	onDayClick: (date: Date) => void;
}

const WEEKDAYS = [
	{
		abbr: __(
			'Monday',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		),
		short: __('Mo', 'out-the-door-order-tasks-workflows-for-woocommerce'),
	},
	{
		abbr: __(
			'Tuesday',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		),
		short: __('Tu', 'out-the-door-order-tasks-workflows-for-woocommerce'),
	},
	{
		abbr: __(
			'Wednesday',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		),
		short: __('We', 'out-the-door-order-tasks-workflows-for-woocommerce'),
	},
	{
		abbr: __(
			'Thursday',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		),
		short: __('Th', 'out-the-door-order-tasks-workflows-for-woocommerce'),
	},
	{
		abbr: __(
			'Friday',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		),
		short: __('Fr', 'out-the-door-order-tasks-workflows-for-woocommerce'),
	},
	{
		abbr: __(
			'Saturday',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		),
		short: __('Sa', 'out-the-door-order-tasks-workflows-for-woocommerce'),
	},
	{
		abbr: __(
			'Sunday',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		),
		short: __('Su', 'out-the-door-order-tasks-workflows-for-woocommerce'),
	},
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
