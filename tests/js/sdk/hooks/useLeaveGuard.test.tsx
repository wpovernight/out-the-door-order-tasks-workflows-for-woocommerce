import React from 'react';
import { render } from '@testing-library/react';
import { confirmLeave, hasLeaveGuard, useLeaveGuard } from '@sdk';
import type { LeaveCheck } from '@sdk';

const Guard = ({ active, ask }: { active: boolean; ask: LeaveCheck }) => {
	useLeaveGuard(active, ask);
	return null;
};

const unloadPrevented = (): boolean => {
	const event = new Event('beforeunload', { cancelable: true });
	const preventDefault = jest.spyOn(event, 'preventDefault');
	window.dispatchEvent(event);
	return preventDefault.mock.calls.length > 0;
};

describe('useLeaveGuard', () => {
	it('lets the screen be left when nothing guards it', async () => {
		expect(hasLeaveGuard()).toBe(false);
		await expect(confirmLeave()).resolves.toBe(true);
		expect(unloadPrevented()).toBe(false);
	});

	it('asks only while active, and stops when unmounted', async () => {
		const ask = jest.fn(() => false);
		const { rerender, unmount } = render(
			<Guard active={false} ask={ask} />
		);

		expect(hasLeaveGuard()).toBe(false);

		rerender(<Guard active ask={ask} />);
		expect(hasLeaveGuard()).toBe(true);
		await expect(confirmLeave()).resolves.toBe(false);
		expect(ask).toHaveBeenCalledTimes(1);
		expect(unloadPrevented()).toBe(true);

		unmount();
		expect(hasLeaveGuard()).toBe(false);
		await expect(confirmLeave()).resolves.toBe(true);
		expect(unloadPrevented()).toBe(false);
	});

	it('asks the latest check given, and waits for an answer', async () => {
		const { rerender, unmount } = render(
			<Guard active ask={() => false} />
		);
		rerender(<Guard active ask={() => Promise.resolve(true)} />);

		await expect(confirmLeave()).resolves.toBe(true);
		unmount();
	});

	it('stops at the first guard that refuses', async () => {
		const later = jest.fn(() => true);
		const { unmount } = render(
			<>
				<Guard active ask={() => false} />
				<Guard active ask={later} />
			</>
		);

		await expect(confirmLeave()).resolves.toBe(false);
		expect(later).not.toHaveBeenCalled();
		unmount();
	});
});
