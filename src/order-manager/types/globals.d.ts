/**
 * Global type declarations for the Out the Door Order Manager.
 */
import type { StatusRoles } from '@sdk';

export interface WpoOtdOrderManagerData {
	apiRoot: string;
	apiNamespace: string;
	nonce: string;
	statusRoles: StatusRoles;
}

declare global {
	interface Window {
        WPO_OTD_OrderManager: WpoOtdOrderManagerData;
	}
}

// This export is necessary to make this a module
export {};
