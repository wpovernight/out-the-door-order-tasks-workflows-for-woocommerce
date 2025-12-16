import { useLocalized } from '@shared/hooks/useLocalized';
import { WpoAomOrderEditMetaBoxData } from '@orderEdit/types/globals';

/**
 * Hook to access Order Edit metabox localized data.
 *
 * @return {WpoAomOrderEditMetaBoxData} The Order Edit localized data object.
 * @throws {Error} If the Order Edit data is not found on the window object.
 */
export function useOrderEditData(): WpoAomOrderEditMetaBoxData {
	return useLocalized<WpoAomOrderEditMetaBoxData>('WPO_AOM_OrderEdit_MetaBox');
}
