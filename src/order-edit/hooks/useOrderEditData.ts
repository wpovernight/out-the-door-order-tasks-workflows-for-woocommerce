import { useLocalized } from '@sdk';
import { WpoOtdOrderEditMetaBoxData } from '@orderEdit/types/globals';

/**
 * Hook to access Order Edit metabox localized data.
 *
 * @return {WpoOtdOrderEditMetaBoxData} The Order Edit localized data object.
 * @throws {Error} If the Order Edit data is not found on the window object.
 */
export function useOrderEditData(): WpoOtdOrderEditMetaBoxData {
	return useLocalized<WpoOtdOrderEditMetaBoxData>(
		'WPO_OTD_OrderEdit_MetaBox'
	);
}
