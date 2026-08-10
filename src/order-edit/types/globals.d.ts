/**
 * Global type declarations for the WPO AOM Order Edit metabox.
 */

import type { StatusRoles } from '@sdk/types/task';

export interface WpoAomOrderEditMetaBoxData {
	apiRoot: string;
	apiNamespace: string;
	nonce: string;
	orderId: number;
	isWooFulfillmentsEnabled: boolean;
	archivePageUrl: string;
	statusRoles: StatusRoles;
}

declare global {
	interface Window {
        WPO_AOM_OrderEdit_MetaBox: WpoAomOrderEditMetaBoxData;
	}
}

// This export is necessary to make this a module
export {};
