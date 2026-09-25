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
					{__('Date Range Preset', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</span>
			</label>
			<select
				id="date-range-preset-select"
				value={value}
				onChange={(e) => onChange(e.target.value as DateRangePreset)}
			>
				<option value="today">
					{__('Today', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</option>
				<option value="tomorrow">
					{__('Tomorrow', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</option>
				<option value="yesterday">
					{__('Yesterday', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</option>
				<option value="current-week">
					{__('Current Week', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</option>
				<option value="next-week">
					{__('Next Week', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</option>
				<option value="last-week">
					{__('Last Week', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</option>
				<option value="current-month">
					{__('Current Month', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</option>
				<option value="next-month">
					{__('Next Month', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</option>
				<option value="last-month">
					{__('Last Month', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</option>
				<option value="custom">
					{__('Custom', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</option>
			</select>
		</div>
	);
};

export default DateRangePresetSelector;
