import React from 'react';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ToastDuration, ToastProvider, ToastType, useToast } from '@sdk';
import type { ToastOptions } from '@sdk';

let addToast: (toast: ToastOptions) => number = () => -1;
let dismissToast: (id: number) => void = () => undefined;

const Grab = () => {
	({ addToast, dismissToast } = useToast());
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

describe('dismissToast', () => {
	it('closes the toast whose id addToast returned, and only that one', () => {
		renderProvider();
		let first = -1;
		let second = -1;
		act(() => {
			first = addToast({ title: 'First.' });
			second = addToast({ title: 'Second.' });
		});
		expect(first).not.toBe(second);

		act(() => dismissToast(second));

		expect(toastNamed('Second.')).toHaveClass('is-exiting');
		expect(toastNamed('First.')).not.toHaveClass('is-exiting');
		act(() => {
			jest.advanceTimersByTime(400);
		});
		expect(screen.queryByText('Second.')).toBeNull();
		expect(screen.getByText('First.')).toBeInTheDocument();
	});

	it('ignores an id already closed', () => {
		renderProvider();
		let id = -1;
		act(() => {
			id = addToast({ title: 'Saved.' });
		});
		act(() => dismissToast(id));
		act(() => {
			jest.advanceTimersByTime(400);
		});

		act(() => {
			addToast({ title: 'Next.' });
			dismissToast(id);
		});
		expect(toastNamed('Next.')).not.toHaveClass('is-exiting');
	});
});
