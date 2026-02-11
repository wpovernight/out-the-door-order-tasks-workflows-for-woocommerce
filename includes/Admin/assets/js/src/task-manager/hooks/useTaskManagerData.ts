import { useLocalized } from '@shared/hooks/useLocalized';
import { WpoAomTaskManagerData } from '@taskManager/types/globals';

/**
 * Hook to access Task Manager localized data.
 *
 * @return {WpoAomTaskManagerData} The Task Manager localized data object.
 * @throws {Error} If the Task Manager data is not found on the window object.
 */
export function useTaskManagerData(): WpoAomTaskManagerData {
	return useLocalized<WpoAomTaskManagerData>('WPO_AOM_TaskManager');
}
