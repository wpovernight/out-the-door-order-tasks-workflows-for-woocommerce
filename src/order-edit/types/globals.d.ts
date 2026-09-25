/**
 * Global type declarations for the WPO AOM Order Edit metabox.
 */

import type { StatusRoles } from '@sdk';

export interface WpoOtdOrderEditMetaBoxData {
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
        WPO_OTD_OrderEdit_MetaBox: WpoOtdOrderEditMetaBoxData;
	}
}

// This export is necessary to make this a module
export {};
