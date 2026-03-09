import { Task, FieldOption, TaskField } from '../types/task';
import { WooFulfillment } from '../types/wooFulfillment';
import { FulfillmentOrder } from '@shared/types/fulfillment';
import { CustomOrderStatus } from '@shared/types/customOrderStatus';

function getApiConfig() {
	const orderManagerData = (window as any).WPO_AOM_OrderManager;
	const orderEditMetaBoxData = (window as any).WPO_AOM_OrderEdit_MetaBox;

	const config = orderManagerData || orderEditMetaBoxData;

	if (!config) {
		console.warn('⚠️ API configuration not found. API calls will fail.');
		return { apiRoot: '', apiNamespace: '', nonce: '' };
	}

	return {
		apiRoot: config.apiRoot,
		apiNamespace: config.apiNamespace,
		nonce: config.nonce,
	};
}

function getApiRoot(): string {
	return getApiConfig().apiRoot;
}

function getApiNamespace(): string {
	return getApiConfig().apiNamespace;
}

function getHeaders(): Record<string, string> {
	return {
		'Content-Type': 'application/json',
		'X-WP-Nonce': getApiConfig().nonce,
	};
}

/**
 * Handles the API response, checking for errors and parsing JSON.
 *
 * @template T - The expected type of the response data.
 * @param  response
 *
 * @return {Promise<T>} The parsed JSON data.
 * @throws Will throw an error if the response is not ok.
 */
async function handleResponse<T>(response: Response): Promise<T> {
	if (!response.ok) {
		const errorText = await response.text();
		throw new Error(`API request failed: ${response.status}: ${errorText}`);
	}

	if (response.status === 204) {
		return undefined as T;
	}

	return response.json() as Promise<T>;
}

/**
 * Fetches tasks from the API and maps custom fields to task properties.
 *
 * @return {Promise<Task[]>} A promise that resolves to an array of tasks.
 * @throws Will throw an error if the API request fails.
 */
export async function fetchTasks(): Promise<Task[]> {
	const response = await fetch(`${getApiRoot()}/${getApiNamespace()}/tasks`, {
		method: 'GET',
		credentials: 'include',
		headers: getHeaders(),
	});

	const data = await handleResponse<any[]>(response);

	return data.map((task: any) => {
		const statusField = task.fields.find(
			(field: any) => field.slug === 'status'
		);
		const positionField = task.fields.find(
			(field: any) => field.slug === 'position'
		);

		return {
			...task,
			status: statusField?.values?.[0]?.resolved?.slug ?? undefined,
			position: positionField?.values?.[0]?.raw ?? undefined,
		};
	});
}

/**
 * Creates a new task with the given payload.
 *
 * @param {Partial<Task>} payload - The task data to create.
 *
 * @return {Promise<Task>} A promise that resolves to the created task.
 * @throws Will throw an error if the API request fails.
 */
export async function createTask(payload: Partial<Task>): Promise<Task> {
	const response = await fetch(`${getApiRoot()}/${getApiNamespace()}/tasks`, {
		method: 'POST',
		credentials: 'include',
		headers: getHeaders(),
		body: JSON.stringify(payload),
	});

	const task = await handleResponse<any>(response);

	// Apply the same mapping as fetchTasks
	const statusField = task.fields?.find(
		(field: any) => field.slug === 'status'
	);
	const positionField = task.fields?.find(
		(field: any) => field.slug === 'position'
	);

	return {
		...task,
		status: statusField?.values?.[0]?.resolved?.slug ?? undefined,
		position: positionField?.values?.[0]?.raw ?? undefined,
	};
}

export async function updateTask(
	taskId: number,
	payload: Partial<Task>
): Promise<Task> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/tasks/${taskId}`,
		{
			method: 'PUT',
			credentials: 'include',
			headers: getHeaders(),
			body: JSON.stringify(payload),
		}
	);

	const task = await handleResponse<any>(response);

	// Apply the same mapping as fetchTasks
	const statusField = task.fields?.find(
		(field: any) => field.slug === 'status'
	);
	const positionField = task.fields?.find(
		(field: any) => field.slug === 'position'
	);

	return {
		...task,
		status: statusField?.values?.[0]?.resolved?.slug ?? undefined,
		position: positionField?.values?.[0]?.raw ?? undefined,
	};
}

export async function deleteTask(taskId: number): Promise<void> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/tasks/${taskId}`,
		{
			method: 'DELETE',
			credentials: 'include',
			headers: getHeaders(),
		}
	);

	return handleResponse<void>(response);
}

/**
 * Moves a task to a new status and position.
 *
 * @param {number}        taskId         - The ID of the task to move.
 * @param {number | null} previousTaskId - The ID of the task that will precede the moved task in the new status, or null if it will be the first task.
 * @param {number}        targetStatusId - The ID of the target status.
 *
 * @return {Promise<{ new_position: number }>} A promise that resolves with the new position when the task has been moved.
 * @throws Will throw an error if the API request fails.
 */
export async function moveTask(
	taskId: number,
	previousTaskId: number | null,
	targetStatusId: number
): Promise<{ new_position: number }> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/tasks/${taskId}/move`,
		{
			method: 'POST',
			credentials: 'include',
			headers: getHeaders(),
			body: JSON.stringify({
				previous_task_id: previousTaskId,
				target_status_id: targetStatusId,
			}),
		}
	);

	return handleResponse<{ new_position: number }>(response);
}

/**
 * Fetches task fields from the API.
 *
 * @return {Promise<Record<string, TaskField>>} A promise that resolves to a record of task fields.
 * @throws Will throw an error if the API request fails.
 */
export async function fetchTaskFields(): Promise<Record<string, TaskField>> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/tasks/fields`,
		{
			method: 'GET',
			credentials: 'include',
			headers: getHeaders(),
		}
	);

	return handleResponse<Record<string, TaskField>>(response);
}

/**
 * Fetches options for a specific field.
 *
 * @param  fieldSlug
 * @return {Promise<FieldOption[]>} A promise that resolves to an array of field options.
 * @throws Will throw an error if the API request fails.
 */
export async function fetchFieldOptions(
	fieldSlug: string
): Promise<FieldOption[]> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/tasks/fields/${fieldSlug}/options`,
		{
			method: 'GET',
			credentials: 'include',
			headers: getHeaders(),
		}
	);

	return handleResponse<FieldOption[]>(response);
}

/**
 * Reorders field options by updating their position values.
 *
 * @param  fieldId          - The field ID whose options are being reordered
 * @param  orderedOptionIds - Array of option IDs in the desired order
 * @return {Promise<{ success: boolean; message: string }>} A promise that resolves to the API response.
 * @throws Will throw an error if the API request fails.
 */
export async function reorderFieldOptions(
	fieldId: number,
	orderedOptionIds: number[]
): Promise<{ success: boolean; message: string }> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/tasks/fields/${fieldId}/options/reorder`,
		{
			method: 'POST',
			credentials: 'include',
			headers: getHeaders(),
			body: JSON.stringify({
				ordered_option_ids: orderedOptionIds,
			}),
		}
	);

	return handleResponse<{ success: boolean; message: string }>(response);
}

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

/**
 * Marks a task as finished.
 *
 * @param  taskId - The ID of the task to finish.
 * @return {Promise<boolean>} A promise that resolves to true if the task was successfully finished.
 * @throws Will throw an error if the API request fails.
 */
export async function finishTask(taskId: number): Promise<boolean> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/tasks/${taskId}/finish`,
		{
			method: 'POST',
			credentials: 'include',
			headers: getHeaders(),
		}
	);

	const data = await handleResponse<{ success: boolean }>(response);
	return data.success;
}

/**
 * Archive a task.
 *
 * @param  taskId - The ID of the task to archive.
 * @return {Promise<boolean>} A promise that resolves to true if the task was successfully finished.
 * @throws Will throw an error if the API request fails.
 */
export async function archiveTask(taskId: number): Promise<boolean> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/tasks/${taskId}/archive`,
		{
			method: 'POST',
			credentials: 'include',
			headers: getHeaders(),
		}
	);

	const data = await handleResponse<{ success: boolean }>(response);
	return data.success;
}

export async function unarchiveTask(taskId: number): Promise<boolean> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/tasks/${taskId}/unarchive`,
		{
			method: 'POST',
			credentials: 'include',
			headers: getHeaders(),
		}
	);

	const data = await handleResponse<{ success: boolean }>(response);
	return data.success;
}

export async function fetchFulfillmentOrders(
	status?: string
): Promise<FulfillmentOrder[]> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/fulfillments/orders` +
			(status ? `?status=${encodeURIComponent(status)}` : ''),
		{
			method: 'GET',
			credentials: 'include',
			headers: getHeaders(),
		}
	);

	return handleResponse<any[]>(response);
}

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
