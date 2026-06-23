/**
 * Global type declarations for the WPO AOM Task Manager plugin.
 */

export interface WpoAomTaskManagerData {
	apiRoot: string;
	apiNamespace: string;
	nonce: string;
}

declare global {
	interface Window {
		WPO_AOM_TaskManager: WpoAomTaskManagerData;
	}
}

// This export is necessary to make this a module
export {};
