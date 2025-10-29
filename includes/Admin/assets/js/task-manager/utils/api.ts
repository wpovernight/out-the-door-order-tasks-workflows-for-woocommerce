import { Task, FieldOption } from '../types/task';

const apiRoot = (window as any).WPO_AOM_TaskManager?.apiRoot;
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
	const response = await fetch(`${apiRoot}/tasks`, {
		method: 'GET',
		credentials: 'include',
		headers: { 'X-WP-Nonce': nonce },
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
			column: statusField?.values?.[0]?.raw ?? undefined,
			position: positionField?.values?.[0]?.raw ?? undefined,
		};
	});
}

// ToDo: Update this function
export async function updateTask(
	taskId: number,
	payload: Partial<Task>
): Promise<Task> {
	const response = await fetch(`${apiRoot}/tasks/${taskId}`, {
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

/**
 * Fetches the available status options from the API.
 *
 * @return {Promise<FieldOption[]>} A promise that resolves to an array of column names.
 * @throws Will throw an error if the API request fails.
 */
export async function fetchStatus(): Promise<FieldOption[]> {
	const response = await fetch(`${apiRoot}/tasks/fields/status/options`, {
		method: 'GET',
		credentials: 'include',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
	});

	return handleResponse<FieldOption[]>(response);
}
