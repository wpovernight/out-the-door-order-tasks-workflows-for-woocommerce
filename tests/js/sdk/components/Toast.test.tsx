import React from 'react';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ToastDuration, ToastProvider, ToastType, useToast } from '@sdk';
import type { ToastOptions } from '@sdk';

let addToast: (toast: ToastOptions) => void = () => undefined;

const Grab = () => {
	addToast = useToast().addToast;
	return null;
};

const renderProvider = () =>
	render(
		<ToastProvider>
			<Grab />
		</ToastProvider>
	);

const toastNamed = (title: string): HTMLElement => {
	const toast = screen.getByText(title).closest('.wpo-otd-toast');
	if (!(toast instanceof HTMLElement)) {
		throw new Error(`No toast "${title}"`);
	}
	return toast;
};

beforeEach(() => {
	jest.useFakeTimers();
});

afterEach(() => {
	jest.useRealTimers();
});

describe('Toast action', () => {
	it('shows no action button without an action', () => {
		renderProvider();
		act(() => addToast({ title: 'Saved.' }));

		const toast = toastNamed('Saved.');
		expect(toast.querySelector('.toast-action')).toBeNull();
		expect(toast.querySelectorAll('button')).toHaveLength(1);
		expect(toast.querySelector('button')).toHaveClass('close-toast');
	});

	it('shows the action before the close button, and runs it then closes the toast', async () => {
		const user = userEvent.setup({
			advanceTimers: jest.advanceTimersByTime,
		});
		const onClick = jest.fn();
		renderProvider();
		act(() =>
			addToast({
				title: 'Order saved.',
				message: 'A now runs after B.',
				type: ToastType.SUCCESS,
				duration: ToastDuration.LONG,
				action: { label: 'Undo', onClick },
			})
		);

		const toast = toastNamed('Order saved.');
		const buttons = toast.querySelectorAll('button');
		expect(buttons).toHaveLength(2);
		expect(buttons[0]).toHaveClass('wpo-button', 'toast-action');
		expect(buttons[0]).toHaveTextContent('Undo');
		expect(buttons[1]).toHaveClass('close-toast');

		await user.click(screen.getByRole('button', { name: 'Undo' }));

		expect(onClick).toHaveBeenCalledTimes(1);
		expect(toast).toHaveClass('is-exiting');
		act(() => {
			jest.advanceTimersByTime(400);
		});
		expect(screen.queryByText('Order saved.')).toBeNull();
	});

	it('keeps the action unused when the toast is closed', async () => {
		const user = userEvent.setup({
			advanceTimers: jest.advanceTimersByTime,
		});
		const onClick = jest.fn();
		renderProvider();
		act(() =>
			addToast({
				title: 'Order saved.',
				action: { label: 'Undo', onClick },
			})
		);

		await user.click(screen.getByRole('button', { name: 'Close' }));

		expect(onClick).not.toHaveBeenCalled();
		expect(toastNamed('Order saved.')).toHaveClass('is-exiting');
	});
});
