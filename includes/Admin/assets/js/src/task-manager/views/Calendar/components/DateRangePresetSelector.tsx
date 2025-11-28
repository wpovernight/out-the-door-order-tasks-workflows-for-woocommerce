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
	const { i18n } = useTaskManagerData();

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
					{i18n.calendar.dateRangePresets.today}
				</option>
				<option value="yesterday">
					{i18n.calendar.dateRangePresets.yesterday}
				</option>
				<option value="current-week">
					{i18n.calendar.dateRangePresets.currentWeek}
				</option>
				<option value="last-week">
					{i18n.calendar.dateRangePresets.lastWeek}
				</option>
				<option value="current-month">
					{i18n.calendar.dateRangePresets.currentMonth}
				</option>
				<option value="last-month">
					{i18n.calendar.dateRangePresets.lastMonth}
				</option>
				<option value="custom">
					{i18n.calendar.dateRangePresets.custom}
				</option>
			</select>
		</div>
	);
};

export default DateRangePresetSelector;
