/**
 * Global type declarations for the WPO AOM Task Manager plugin.
 */

export interface WpoOtdTaskManagerData {
	apiRoot: string;
	apiNamespace: string;
	nonce: string;
}

declare global {
	interface Window {
		WPO_OTD_TaskManager: WpoOtdTaskManagerData;
	}
}

// This export is necessary to make this a module
export {};
