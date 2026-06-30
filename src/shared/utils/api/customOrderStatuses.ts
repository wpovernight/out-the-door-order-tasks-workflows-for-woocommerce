import { CustomOrderStatus } from '@shared/types/customOrderStatus';
import {
	getApiRoot,
	getApiNamespace,
	getHeaders,
	handleResponse,
} from './client';

export async function fetchCustomOrderStatuses(): Promise<CustomOrderStatus[]> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/custom-order-statuses`,
		{
			method: 'GET',
			credentials: 'include',
			headers: getHeaders(),
		}
	);

	return handleResponse<CustomOrderStatus[]>(response);
}

export async function createCustomOrderStatus(
	payload: Partial<CustomOrderStatus>
): Promise<CustomOrderStatus> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/custom-order-statuses`,
		{
			method: 'POST',
			credentials: 'include',
			headers: getHeaders(),
			body: JSON.stringify(payload),
		}
	);

	return handleResponse<CustomOrderStatus>(response);
}

export async function updateCustomOrderStatus(
	statusId: number,
	payload: Partial<CustomOrderStatus>
): Promise<CustomOrderStatus> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/custom-order-statuses/${statusId}`,
		{
			method: 'PUT',
			credentials: 'include',
			headers: getHeaders(),
			body: JSON.stringify(payload),
		}
	);

	return handleResponse<CustomOrderStatus>(response);
}

export async function deleteCustomOrderStatus(statusId: number): Promise<void> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/custom-order-statuses/${statusId}`,
		{
			method: 'DELETE',
			credentials: 'include',
			headers: getHeaders(),
		}
	);

	return handleResponse<void>(response);
}
