import { Task, FieldOption } from '../types/task';

const apiRoot = (window as any).WPO_AOM_TaskManager?.apiRoot;
const apiNamespace = (window as any).WPO_AOM_TaskManager?.apiNamespace;
const nonce = (window as any).WPO_AOM_TaskManager?.nonce;

if (!apiRoot) {
	// eslint-disable-next-line no-console
	console.warn('⚠️ API Root is missing. API calls will fail.');
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

	return response.json() as Promise<T>;
}

/**
 * Fetches tasks from the API and maps custom fields to task properties.
 *
 * @return {Promise<Task[]>} A promise that resolves to an array of tasks.
 * @throws Will throw an error if the API request fails.
 */
export async function fetchTasks(): Promise<Task[]> {
	const response = await fetch(`${apiRoot}/${apiNamespace}/tasks`, {
		method: 'GET',
		credentials: 'include',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
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
 * Moves a task to a new status and position.
 *
 * @param {number}        taskId         - The ID of the task to move.
 * @param {number | null} previousTaskId - The ID of the task that will precede the moved task in the new status, or null if it will be the first task.
 * @param {number}        targetStatusId - The ID of the target status.
 *
 * @return {Promise<void>} A promise that resolves when the task has been moved.
 * @throws Will throw an error if the API request fails.
 */
export async function moveTask(
	taskId: number,
	previousTaskId: number | null,
	targetStatusId: number
): Promise<void> {
	const response = await fetch(
		`${apiRoot}/${apiNamespace}/tasks/${taskId}/move`,
		{
			method: 'POST',
			credentials: 'include',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce,
			},
			body: JSON.stringify({
				previous_task_id: previousTaskId,
				target_status_id: targetStatusId,
			}),
		}
	);

	return handleResponse<void>(response);
}

// ToDo: Update this function
export async function updateTask(
	taskId: number,
	payload: Partial<Task>
): Promise<Task> {
	const response = await fetch(`${apiRoot}/${apiNamespace}/tasks/${taskId}`, {
		method: 'PUT',
		credentials: 'include',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
		body: JSON.stringify(payload),
	});

	return handleResponse<Task>(response);
}

export async function fetchFieldOptions(
	fieldSlug: string
): Promise<FieldOption[]> {
	const response = await fetch(
		`${apiRoot}/${apiNamespace}/tasks/fields/${fieldSlug}/options`,
		{
			method: 'GET',
			credentials: 'include',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce,
			},
		}
	);

	return handleResponse<FieldOption[]>(response);
}

/**
 * Fetches the available status options from the API.
 *
 * @return {Promise<FieldOption[]>} A promise that resolves to an array of column names.
 * @throws Will throw an error if the API request fails.
 */
export const fetchStatus = (): Promise<FieldOption[]> =>
	fetchFieldOptions('status');

/**
 * Searches orders based on a search term.
 *
 * @param  term
 * @return {Promise<any[]>} A promise that resolves to an array of orders.
 * @throws Will throw an error if the API request fails.
 */
export const searchOrders = (term: string) => {
	return fetch(`${apiRoot}/orders?search=${encodeURIComponent(term)}`, {
		method: 'GET',
		credentials: 'include',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
	}).then(handleResponse<any[]>);
};
