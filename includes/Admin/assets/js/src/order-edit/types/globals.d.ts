/**
 * Global type declarations for the WPO AOM Order Edit metabox.
 */

export interface WpoAomOrderEditMetaBoxData {
	apiRoot: string;
	apiNamespace: string;
	nonce: string;
	orderId: number;
	isWooFulfillmentsEnabled: boolean;
}

declare global {
	interface Window {
        WPO_AOM_OrderEdit_MetaBox: WpoAomOrderEditMetaBoxData;
	}
}

// This export is necessary to make this a module
export {};
