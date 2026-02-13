import React from 'react';
import { __ } from '@wordpress/i18n';
import { DateRangePreset } from '../context/ViewTaskContext';

interface DateRangePresetSelectorProps {
	value: DateRangePreset;
	onChange: (preset: DateRangePreset) => void;
}

const DateRangePresetSelector: React.FC<DateRangePresetSelectorProps> = ({
	value,
	onChange,
}) => {
	return (
		<div className="calendar-date-range-preset-selector">
			<label htmlFor="date-range-preset-select">
				<span className="screenReader">
					{__('Date Range Preset', 'wpo-aom')}
				</span>
			</label>
			<select
				id="date-range-preset-select"
				value={value}
				onChange={(e) => onChange(e.target.value as DateRangePreset)}
			>
				<option value="today">{__('Today', 'wpo-aom')}</option>
				<option value="tomorrow">{__('Tomorrow', 'wpo-aom')}</option>
				<option value="yesterday">{__('Yesterday', 'wpo-aom')}</option>
				<option value="current-week">
					{__('Current Week', 'wpo-aom')}
				</option>
				<option value="next-week">{__('Next Week', 'wpo-aom')}</option>
				<option value="last-week">{__('Last Week', 'wpo-aom')}</option>
				<option value="current-month">
					{__('Current Month', 'wpo-aom')}
				</option>
				<option value="next-month">
					{__('Next Month', 'wpo-aom')}
				</option>
				<option value="last-month">
					{__('Last Month', 'wpo-aom')}
				</option>
				<option value="custom">{__('Custom', 'wpo-aom')}</option>
			</select>
		</div>
	);
};

export default DateRangePresetSelector;
