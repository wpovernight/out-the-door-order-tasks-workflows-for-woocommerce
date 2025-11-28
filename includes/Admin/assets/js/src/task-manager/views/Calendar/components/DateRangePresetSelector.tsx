import React from 'react';
import { DateRangePreset } from '../context/ViewTaskContext';
import { useTaskManagerData } from '@taskManager/hooks/useTaskManagerData';

interface DateRangePresetSelectorProps {
	value: DateRangePreset;
	onChange: (preset: DateRangePreset) => void;
}

const DateRangePresetSelector: React.FC<DateRangePresetSelectorProps> = ({
	value,
	onChange,
}) => {
	const localized = useTaskManagerData();

	return (
		<div className="calendar-date-range-preset-selector">
			<label htmlFor="date-range-preset-select">
				<span className="screenReader">Date Range Preset</span>
			</label>
			<select
				id="date-range-preset-select"
				value={value}
				onChange={(e) => onChange(e.target.value as DateRangePreset)}
			>
				<option value="today">
					{localized.calendar.dateRangePresets.today}
				</option>
				<option value="yesterday">
					{localized.calendar.dateRangePresets.yesterday}
				</option>
				<option value="current-week">
					{localized.calendar.dateRangePresets.currentWeek}
				</option>
				<option value="last-week">
					{localized.calendar.dateRangePresets.lastWeek}
				</option>
				<option value="current-month">
					{localized.calendar.dateRangePresets.currentMonth}
				</option>
				<option value="last-month">
					{localized.calendar.dateRangePresets.lastMonth}
				</option>
				<option value="custom">
					{localized.calendar.dateRangePresets.custom}
				</option>
			</select>
		</div>
	);
};

export default DateRangePresetSelector;
