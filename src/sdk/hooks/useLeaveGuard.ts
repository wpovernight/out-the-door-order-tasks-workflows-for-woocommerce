import { useEffect, useRef } from 'react';

/**
 * Asked before the screen is left: true lets it go, false keeps it.
 */
export type LeaveCheck = () => boolean | Promise<boolean>;

const guards = new Set<() => boolean | Promise<boolean>>();

/**
 * Whether a view is guarding against being left.
 */
export function hasLeaveGuard(): boolean {
	return guards.size > 0;
}

/**
 * Ask every view guarding against being left, in the order they began, until
 * one refuses. With none, it may be left.
 *
 * @return Whether the screen may be left.
 */
export async function confirmLeave(): Promise<boolean> {
	for (const guard of Array.from(guards)) {
		if (!(await guard())) {
			return false;
		}
	}

	return true;
}

/**
 * Guard a view against being left while it is active, as with unsaved
 * changes: the order manager's tabs ask the check before they switch, and the
 * browser asks before the page is closed or reloaded.
 *
 * @param active Whether to guard now.
 * @param ask    Asked before a tab switch; the latest one given is asked.
 */
export function useLeaveGuard(active: boolean, ask: LeaveCheck): void {
	const askRef = useRef(ask);
	askRef.current = ask;

	useEffect(() => {
		if (!active) {
			return undefined;
		}

		const guard = () => askRef.current();
		const onBeforeUnload = (event: BeforeUnloadEvent) => {
			event.preventDefault();
			// Older browsers ask only when a return value is set.
			event.returnValue = '';
		};

		guards.add(guard);
		window.addEventListener('beforeunload', onBeforeUnload);

		return () => {
			guards.delete(guard);
			window.removeEventListener('beforeunload', onBeforeUnload);
		};
	}, [active]);
}
