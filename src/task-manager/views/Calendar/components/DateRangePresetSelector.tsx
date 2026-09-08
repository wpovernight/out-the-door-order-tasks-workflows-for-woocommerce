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
					{__('Date Range Preset', 'advanced-order-manager-for-woocommerce')}
				</span>
			</label>
			<select
				id="date-range-preset-select"
				value={value}
				onChange={(e) => onChange(e.target.value as DateRangePreset)}
			>
				<option value="today">
					{__('Today', 'advanced-order-manager-for-woocommerce')}
				</option>
				<option value="tomorrow">
					{__('Tomorrow', 'advanced-order-manager-for-woocommerce')}
				</option>
				<option value="yesterday">
					{__('Yesterday', 'advanced-order-manager-for-woocommerce')}
				</option>
				<option value="current-week">
					{__('Current Week', 'advanced-order-manager-for-woocommerce')}
				</option>
				<option value="next-week">
					{__('Next Week', 'advanced-order-manager-for-woocommerce')}
				</option>
				<option value="last-week">
					{__('Last Week', 'advanced-order-manager-for-woocommerce')}
				</option>
				<option value="current-month">
					{__('Current Month', 'advanced-order-manager-for-woocommerce')}
				</option>
				<option value="next-month">
					{__('Next Month', 'advanced-order-manager-for-woocommerce')}
				</option>
				<option value="last-month">
					{__('Last Month', 'advanced-order-manager-for-woocommerce')}
				</option>
				<option value="custom">
					{__('Custom', 'advanced-order-manager-for-woocommerce')}
				</option>
			</select>
		</div>
	);
};

export default DateRangePresetSelector;
