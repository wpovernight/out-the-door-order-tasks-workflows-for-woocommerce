import React, { useState, useEffect } from 'react';

export type AsyncLoaderStatus = 'idle' | 'loading' | 'loaded' | 'error';

interface UseAsyncLoaderResult {
	loadingStatus: AsyncLoaderStatus;
	loadingError: Error | null;
}

/**
 * Generic async loader hook that handles loading/error state
 * for any async function.
 * @param asyncFunction
 * @param dependencies
 */
export function useAsyncLoader(
	asyncFunction: () => Promise<void>,
	dependencies: React.DependencyList = []
): UseAsyncLoaderResult {
	const [status, setStatus] = useState<AsyncLoaderStatus>('idle');
	const [error, setError] = useState<Error | null>(null);

	useEffect(() => {
		let isActive = true;

		(async () => {
			setStatus('loading');
			try {
				await asyncFunction();
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
