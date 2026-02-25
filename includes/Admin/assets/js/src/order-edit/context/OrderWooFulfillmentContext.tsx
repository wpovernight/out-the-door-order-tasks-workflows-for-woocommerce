import React, { useCallback, useContext, useState } from 'react';
import { WooFulfillment, WooFulfillmentItem } from '@shared/types/wooFulfillment';
import { fetchWooFulfillments, fetchOrder } from '@shared/utils/api';
import { AsyncLoaderStatus } from '@shared/hooks/useAsyncLoader';

interface Order {
	line_items: Array<{
		id: number;
		name: string;
	}>;
}

interface OrderWooFulfillmentContextType {
	orderId: number;
	fulfillments: WooFulfillment[];
	loadingStatus: AsyncLoaderStatus;
	loadingError: Error | null;
	loadFulfillments: (force?: boolean) => Promise<void>;
	refreshFulfillments: () => Promise<void>;
	loadOrder: () => Promise<void>;
}

const OrderWooFulfillmentContext = React.createContext<
	OrderWooFulfillmentContextType | undefined
>(undefined);

export const OrderWooFulfillmentProvider: React.FC<{
	orderId: number;
	children: React.ReactNode;
}> = ({ orderId, children }) => {
	const [order, setOrder] = useState<Order | null>(null);
	const [fulfillments, setFulfillments] = useState<WooFulfillment[]>([]);
	const [loadingStatus, setLoadingStatus] =
		useState<AsyncLoaderStatus>('idle');
	const [loadingError, setLoadingError] = useState<Error | null>(null);
	const [hasLoaded, setHasLoaded] = useState<boolean>(false);
	const isLoadingRef = React.useRef<boolean>(false);

	const loadOrder = useCallback(async () => {
		if (order) {
			return;
		}

		try {
			const orderData = await fetchOrder(orderId);
			setOrder(orderData);
		} catch (err) {
			console.error('Error loading order:', err);
		}
	}, [orderId, order]);

	const loadFulfillments = useCallback(
		async (force: boolean = false) => {
			// Prevent concurrent calls
			if (isLoadingRef.current || (hasLoaded && !force)) {
				return;
			}

			isLoadingRef.current = true;
			setLoadingStatus('loading');
			setLoadingError(null);

			try {
				// Load order first if not loaded
				let currentOrder = order;
				if (!currentOrder) {
					currentOrder = await fetchOrder(orderId);
				}

				const fulfillmentData = await fetchWooFulfillments(orderId);

				// Enrich fulfillments with item names from order data.
				const enrichedFulfillments = fulfillmentData.map(
					(fulfillment) => ({
						...fulfillment,
						meta_data: fulfillment.meta_data.map((meta) => {
							if (
								meta.key !== '_items' ||
								!Array.isArray(meta.value)
							) {
								return meta;
							}

							const items = meta.value as WooFulfillmentItem[];
							return {
								...meta,
								value: items.map((item) => {
									const orderItem =
										currentOrder?.line_items?.find(
											(oi) => oi.id === item.item_id
										);
									return {
										...item,
										name: orderItem?.name ?? 'Item',
									};
								}),
							};
						}),
					})
				);

				// Update all state at once to avoid intermediate renders
				setOrder(currentOrder);
				setFulfillments(enrichedFulfillments);
				setHasLoaded(true);
				setLoadingStatus('loaded');
			} catch (err) {
				setLoadingStatus('error');
				setLoadingError(
					err instanceof Error
						? err
						: new Error('Failed to load fulfillments')
				);
				console.error('Error loading fulfillments:', err);
			} finally {
				isLoadingRef.current = false;
			}
		},
		[orderId, hasLoaded, order]
	);

	const refreshFulfillments = useCallback(async () => {
		await loadFulfillments(true);
	}, [loadFulfillments]);

	return (
		<OrderWooFulfillmentContext.Provider
			value={{
				orderId,
				fulfillments,
				loadingStatus,
				loadingError,
				loadFulfillments,
				refreshFulfillments,
				loadOrder,
			}}
		>
			{children}
		</OrderWooFulfillmentContext.Provider>
	);
};

export const useOrderWooFulfillment = (): OrderWooFulfillmentContextType => {
	const context = useContext(OrderWooFulfillmentContext);
	if (!context) {
		throw new Error(
			'useOrderFulfillment must be used within an OrderFulfillmentProvider'
		);
	}
	return context;
};
