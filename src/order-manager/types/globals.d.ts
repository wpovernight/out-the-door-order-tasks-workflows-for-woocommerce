/**
 * Global type declarations for the WPO AOM Order Manager.
 */
import type { StatusRoles } from '@shared/types/task';

export interface WpoAomOrderManagerData {
	apiRoot: string;
	apiNamespace: string;
	nonce: string;
	statusRoles: StatusRoles;
}

declare global {
	interface Window {
        WPO_AOM_OrderManager: WpoAomOrderManagerData;
	}
}

// This export is necessary to make this a module
export {};
