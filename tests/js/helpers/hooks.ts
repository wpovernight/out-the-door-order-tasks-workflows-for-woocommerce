import { actions, filters } from '@wordpress/hooks';

/**
 * Forget every filter and action, with their run counts, on the default
 * wp.hooks instance, so no test sees another's callbacks.
 */
export function resetHooks(): void {
	for (const store of [filters, actions]) {
		for (const hookName of Object.keys(store)) {
			if ('__current' !== hookName) {
				delete store[hookName];
			}
		}
	}
}
