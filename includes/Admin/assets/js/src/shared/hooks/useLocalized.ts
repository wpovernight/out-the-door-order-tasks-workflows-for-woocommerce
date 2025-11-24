import { WPOAOMLocalized } from '../types/globals';

/**
 * Hook to safely access localized data for WPO AOM Task Manager.
 * Provides type-safe access to window.WPO_AOM_TaskManager.
 *
 * @return {WPOAOMLocalized} The localized data object.
 */
export function useLocalized(): WPOAOMLocalized {
	if (!window.WPO_AOM_TaskManager) {
		// eslint-disable-next-line no-console
		console.error(
			'WPO_AOM_TaskManager is not defined. Localized data missing.'
		);

		// Return defaults to prevent crashes
		return {
			apiRoot: '',
			apiNamespace: '',
			nonce: '',
			loading: 'Loading...',
			errorLoading: 'Error loading tasks. Please try again.',
			views: {},
		};
	}

	return window.WPO_AOM_TaskManager;
}
