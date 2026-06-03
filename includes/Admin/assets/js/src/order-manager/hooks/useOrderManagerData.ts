import { useLocalized } from '@shared/hooks/useLocalized';
import { WpoAomOrderEditMetaBoxData } from '@orderEdit/types/globals';
import {WpoAomOrderManagerData} from "@orderManager/types/globals";

/**
 * Hook to access Order Edit metabox localized data.
 *
 * @return {WpoAomOrderManagerData} The Order Edit localized data object.
 * @throws {Error} If the Order Edit data is not found on the window object.
 */
export function useOrderManagerData(): WpoAomOrderManagerData {
	return useLocalized<WpoAomOrderManagerData>(
		'WPO_AOM_OrderManager'
	);
}
