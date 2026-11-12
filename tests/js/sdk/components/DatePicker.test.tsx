import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { DatePicker } from '@sdk';

const trigger = () => document.querySelector('.wpo-otd-datepicker-trigger');

describe('DatePicker', () => {
	it('can be cleared and opened while enabled', async () => {
		const user = userEvent.setup();
		const onChange = jest.fn();
		render(<DatePicker id="day" value="2026-11-03" onChange={onChange} />);

		expect(trigger()).toBeEnabled();
		expect(trigger()).not.toHaveClass('is-invalid');
		expect(trigger()).not.toHaveAttribute('aria-describedby');

		await user.click(trigger() as HTMLElement);
		expect(
			document.querySelector('.wpo-otd-datepicker-dropdown')
		).toBeInTheDocument();

		await user.click(screen.getByRole('button', { name: 'Clear date' }));
		expect(onChange).toHaveBeenCalledWith('');
	});

	it('neither opens nor offers clearing when disabled, but shows its date', async () => {
		const user = userEvent.setup();
		const onChange = jest.fn();
		render(
			<DatePicker
				id="day"
				value="2026-11-03"
				onChange={onChange}
				disabled
			/>
		);

		expect(trigger()).toBeDisabled();
		expect(trigger()).toHaveTextContent('2026');
		expect(
			screen.queryByRole('button', { name: 'Clear date' })
		).not.toBeInTheDocument();

		await user.click(trigger() as HTMLElement);
		expect(
			document.querySelector('.wpo-otd-datepicker-dropdown')
		).not.toBeInTheDocument();
		expect(onChange).not.toHaveBeenCalled();
	});

	it('marks a refused value and names the text describing it', () => {
		render(
			<DatePicker id="day" invalid describedBy="day-help day-error" />
		);

		expect(trigger()).toHaveClass('is-invalid');
		expect(trigger()).not.toHaveAttribute('aria-invalid');
		expect(trigger()).toHaveAttribute(
			'aria-describedby',
			'day-help day-error'
		);
	});
});
