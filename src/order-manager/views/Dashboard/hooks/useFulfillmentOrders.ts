import { useCallback, useState } from 'react';
import {
	fetchFulfillmentOrders,
	PaginationMeta,
	useAsyncLoader,
	FulfillmentOrder,
} from '@sdk';

export interface UseFulfillmentOrdersResult {
	orders: FulfillmentOrder[];
	meta: PaginationMeta | null;
	page: number;
	isLoading: boolean;
	error: Error | null;
	goToPage: (page: number) => void;
}

export function useFulfillmentOrders(
	status = 'partially-fulfilled',
	perPage = 5
): UseFulfillmentOrdersResult {
	const [orders, setOrders] = useState<FulfillmentOrder[]>([]);
	const [meta, setMeta] = useState<PaginationMeta | null>(null);
	const [page, setPage] = useState(1);

	const { loadingStatus, loadingError } = useAsyncLoader(
		async (isActive) => {
			const result = await fetchFulfillmentOrders(status, page, perPage);

			// Ignore a superseded response (page changed, or unmounted) so it
			// doesn't overwrite newer state.
			if (!isActive()) {
				return;
			}

			setOrders(result.data);
			setMeta(result.meta);
		},
		[status, page, perPage]
	);

	const goToPage = useCallback(
		(next: number) => {
			setPage((current) => {
				const lastPage = Math.max(1, meta?.last_page ?? 1);
				const validNextPage = Math.min(Math.max(1, next), lastPage);
				return validNextPage === current ? current : validNextPage;
			});
		},
		[meta]
	);

	return {
		orders,
		meta,
		page,
		isLoading: 'idle' === loadingStatus || 'loading' === loadingStatus,
		error: loadingError,
		goToPage,
	};
}
