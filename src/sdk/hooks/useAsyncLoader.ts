import React, { useState, useEffect } from 'react';

export type AsyncLoaderStatus = 'idle' | 'loading' | 'loaded' | 'error';

interface UseAsyncLoaderResult {
	loadingStatus: AsyncLoaderStatus;
	loadingError: Error | null;
}

/**
 * Generic async loader hook that handles loading/error state
 * for any async function.
 *
 * The callback receives an `isActive()` probe: when the loader re-runs (deps
 * changed) or unmounts, it returns false. Callbacks that set their own state
 * from the resolved value should check it first, so a superseded response
 * doesn't overwrite newer state.
 *
 * @param asyncFunction
 * @param dependencies
 */
export function useAsyncLoader(
	asyncFunction: ( isActive: () => boolean ) => Promise<void>,
	dependencies: React.DependencyList = []
): UseAsyncLoaderResult {
	const [status, setStatus] = useState<AsyncLoaderStatus>('idle');
	const [error, setError] = useState<Error | null>(null);

	useEffect(() => {
		let isActive = true;

		(async () => {
			setStatus('loading');
			try {
				await asyncFunction(() => isActive);
				if (isActive) {
					setStatus('loaded');
				}
			} catch (err) {
				if (isActive) {
					setStatus('error');
					setError(
						err instanceof Error
							? err
							: new Error(
									'Unknown error occurred during async operation'
								)
					);
				}
			}
		})();

		return () => {
			isActive = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, dependencies);

	return { loadingStatus: status, loadingError: error };
}
