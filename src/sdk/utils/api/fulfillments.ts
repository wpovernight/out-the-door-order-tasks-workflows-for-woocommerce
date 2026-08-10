import { FulfillmentOrder } from '@sdk/types/fulfillment';
import { WooFulfillment } from '@sdk/types/wooFulfillment';
import {
	getApiRoot,
	getApiNamespace,
	getHeaders,
	handlePaginated,
	handleResponse,
	Paginated,
} from './client';

export async function fetchFulfillmentOrders(
	status?: string,
	page = 1,
	perPage = 10
): Promise<Paginated<FulfillmentOrder>> {
	const url = new URL(
		`${getApiRoot()}/${getApiNamespace()}/fulfillments/orders`
	);

	if (status) {
		url.searchParams.set('status', status);
	}

	url.searchParams.set('page', String(page));
	url.searchParams.set('per_page', String(perPage));

	const response = await fetch(url.toString(), {
		method: 'GET',
		credentials: 'include',
		headers: getHeaders(),
	});

	return handlePaginated<FulfillmentOrder>(response);
}

/**
 * Fetches fulfillments for a specific order.
 *
 * @param {number} orderId - The order ID to fetch fulfillments for.
 * @return {Promise<WooFulfillment[]>} A promise that resolves to an array of fulfillments.
 * @throws Will throw an error if the API request fails.
 */
export async function fetchWooFulfillments(
	orderId: number
): Promise<WooFulfillment[]> {
	const response = await fetch(
		`${getApiRoot()}/orders/${orderId}/fulfillments`,
		{
			method: 'GET',
			credentials: 'include',
			headers: getHeaders(),
		}
	);

	return handleResponse<WooFulfillment[]>(response);
}
