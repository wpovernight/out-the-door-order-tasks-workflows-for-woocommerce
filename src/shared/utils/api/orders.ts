import {getApiRoot, getHeaders, handleResponse} from "./client";

/**
 * Searches orders based on a search term.
 *
 * @param  term
 * @param  signal
 * @return {Promise<any[]>} A promise that resolves to an array of orders.
 * @throws Will throw an error if the API request fails.
 */
export const searchOrders = (
    term: string,
    signal?: AbortSignal
): Promise<any[]> => {
    return fetch(`${getApiRoot()}/orders?search=${encodeURIComponent(term)}`, {
        method: 'GET',
        credentials: 'include',
        headers: getHeaders(),
        signal,
    }).then(handleResponse<any[]>);
};

/**
 * Fetches a specific order by ID.
 *
 * @param  orderId
 * @return {Promise<any>} A promise that resolves to the order data.
 * @throws Will throw an error if the API request fails.
 */
export async function fetchOrder(orderId: number): Promise<any> {
    const response = await fetch(`${getApiRoot()}/orders/${orderId}`, {
        method: 'GET',
        credentials: 'include',
        headers: getHeaders(),
    });

    return handleResponse(response);
}