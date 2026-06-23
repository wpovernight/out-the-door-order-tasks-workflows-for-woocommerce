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
				<span className="screen-reader-text">
					{__('Date Range Preset', 'wpo-advanced-order-manager')}
				</span>
			</label>
			<select
				id="date-range-preset-select"
				value={value}
				onChange={(e) => onChange(e.target.value as DateRangePreset)}
			>
				<option value="today">
					{__('Today', 'wpo-advanced-order-manager')}
				</option>
				<option value="tomorrow">
					{__('Tomorrow', 'wpo-advanced-order-manager')}
				</option>
				<option value="yesterday">
					{__('Yesterday', 'wpo-advanced-order-manager')}
				</option>
				<option value="current-week">
					{__('Current Week', 'wpo-advanced-order-manager')}
				</option>
				<option value="next-week">
					{__('Next Week', 'wpo-advanced-order-manager')}
				</option>
				<option value="last-week">
					{__('Last Week', 'wpo-advanced-order-manager')}
				</option>
				<option value="current-month">
					{__('Current Month', 'wpo-advanced-order-manager')}
				</option>
				<option value="next-month">
					{__('Next Month', 'wpo-advanced-order-manager')}
				</option>
				<option value="last-month">
					{__('Last Month', 'wpo-advanced-order-manager')}
				</option>
				<option value="custom">
					{__('Custom', 'wpo-advanced-order-manager')}
				</option>
			</select>
		</div>
	);
};

export default DateRangePresetSelector;
