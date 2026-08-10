import { StatusRoles } from '@sdk/types/task';

/**
 * Empty fallback returned when no bootstrap data is present (e.g. in dev or
 * preview contexts). Both roles being null reads as "no roles configured",
 * which consumers should already handle gracefully.
 */
const EMPTY_ROLES: StatusRoles = { done: null, undone: null };

type WindowWithBootstrap = Window & {
	WPO_AOM_OrderManager?: { statusRoles?: StatusRoles };
	WPO_AOM_OrderEdit_MetaBox?: { statusRoles?: StatusRoles };
};

/**
 * Read status-role assignments from whichever screen bootstrap is present.
 */
export function getInitialStatusRoles(): StatusRoles {
	const win = window as WindowWithBootstrap;
	const data = win.WPO_AOM_OrderManager ?? win.WPO_AOM_OrderEdit_MetaBox;

	return data?.statusRoles ?? EMPTY_ROLES;
}
