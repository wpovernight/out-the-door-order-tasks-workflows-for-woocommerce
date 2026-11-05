import type { StatusRoles } from '@sdk';

/**
 * The data the admin screens get from wp_localize_script.
 */
export interface LocalizedScreenData {
	apiRoot: string;
	apiNamespace: string;
	nonce: string;
	statusRoles: StatusRoles;
	[key: string]: unknown;
}

export const TEST_API_ROOT = 'http://localhost/wp-json';
export const TEST_API_NAMESPACE = 'wc/v3/wpo/otd';
export const TEST_NONCE = 'test-nonce';

const setKeys = new Set<string>();

/**
 * Put localized screen data on window, as wp_localize_script does.
 *
 * Unset fields get working defaults. Everything set here is removed after the
 * test.
 *
 * @param globalKey The window key, e.g. 'WPO_OTD_OrderManager'.
 * @param data      The fields to set or override.
 * @return The data now on window.
 */
export function setLocalized(
	globalKey: string,
	data: Partial<LocalizedScreenData> = {}
): LocalizedScreenData {
	const value: LocalizedScreenData = {
		apiRoot: TEST_API_ROOT,
		apiNamespace: TEST_API_NAMESPACE,
		nonce: TEST_NONCE,
		statusRoles: { done: null, undone: null },
		...data,
	};

	(window as unknown as Record<string, unknown>)[globalKey] = value;
	setKeys.add(globalKey);

	return value;
}

/**
 * Remove everything setLocalized() put on window.
 */
export function clearLocalized(): void {
	for (const key of setKeys) {
		delete (window as unknown as Record<string, unknown>)[key];
	}

	setKeys.clear();
}
