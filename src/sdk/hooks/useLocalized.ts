/**
 * Generic hook to safely access localized data passed from PHP via wp_localize_script.
 *
 * @template T - The type of the localized data object.
 * @param {string} globalKey - The key under which the data is stored on the window object.
 * @return {T} The localized data object.
 * @throws {Error} If the data is not found on the window object.
 *
 * @example
 * const data = useLocalized<WpoOtdTaskManagerData>('WPO_OTD_TaskManager');
 */
export function useLocalized<T>(globalKey: string): T {
	const data = (window as any)[globalKey];

	if (!data) {
		throw new Error(
			`Localized data "${globalKey}" not found on the window object`
		);
	}

	return data as T;
}
