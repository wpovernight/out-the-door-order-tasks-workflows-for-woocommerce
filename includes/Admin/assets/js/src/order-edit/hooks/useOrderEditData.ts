import { useLocalized } from '@shared/hooks/useLocalized';
import { WpoAomOrderEditData } from '@orderEdit/types/globals';

/**
 * Hook to access Order Edit metabox localized data.
 *
 * @return {WpoAomOrderEditData} The Order Edit localized data object.
 * @throws {Error} If the Order Edit data is not found on the window object.
 */
export function useOrderEditData(): WpoAomOrderEditData {
	return useLocalized<WpoAomOrderEditData>('WPO_AOM_OrderEdit');
}
