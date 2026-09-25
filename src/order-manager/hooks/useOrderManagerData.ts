import { useLocalized } from '@sdk';
import { WpoOtdOrderEditMetaBoxData } from '@orderEdit/types/globals';
import { WpoOtdOrderManagerData } from '@orderManager/types/globals';

/**
 * Hook to access Order Edit metabox localized data.
 *
 * @return {WpoOtdOrderManagerData} The Order Edit localized data object.
 * @throws {Error} If the Order Edit data is not found on the window object.
 */
export function useOrderManagerData(): WpoOtdOrderManagerData {
	return useLocalized<WpoOtdOrderManagerData>('WPO_OTD_OrderManager');
}
