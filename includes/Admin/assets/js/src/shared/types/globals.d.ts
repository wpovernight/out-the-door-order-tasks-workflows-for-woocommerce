/**
 * Global type declarations for the WPO AOM Task Manager plugin.
 */

export interface WPOAOMLocalized {
	apiRoot: string;
	apiNamespace: string;
	nonce: string;
	loading: string;
	errorLoading: string;
	views: Record<string, string>;
}

declare global {
	interface Window {
		WPO_AOM_TaskManager: WPOAOMLocalized;
	}
}

// This export is necessary to make this a module
export {};
