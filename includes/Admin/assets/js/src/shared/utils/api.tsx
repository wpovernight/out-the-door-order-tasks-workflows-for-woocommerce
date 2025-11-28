import { Task, FieldOption, TaskField } from '../types/task';

// Get API configuration from window object
// This works for both task-manager and order-edit as they both provide the same API config
function getApiConfig() {
	const taskManagerData = (window as any).wpoAomTaskManager;
	const orderEditData = (window as any).wpoAomOrderEdit;

	const config = taskManagerData || orderEditData;

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

const { apiRoot, apiNamespace, nonce } = getApiConfig();

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
 * Creates a new task with the given payload.
 *
 * @param {Partial<Task>} payload - The task data to create.
 *
 * @return {Promise<Task>} A promise that resolves to the created task.
 * @throws Will throw an error if the API request fails.
 */
export async function createTask(payload: Partial<Task>): Promise<Task> {
	const response = await fetch(`${apiRoot}/${apiNamespace}/tasks`, {
		method: 'POST',
		credentials: 'include',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
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
	const response = await fetch(`${apiRoot}/${apiNamespace}/tasks/${taskId}`, {
		method: 'PUT',
		credentials: 'include',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
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

export async function deleteTask(taskId: number): Promise<void> {
	const response = await fetch(`${apiRoot}/${apiNamespace}/tasks/${taskId}`, {
		method: 'DELETE',
		credentials: 'include',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
	});

	return handleResponse<void>(response);
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

/**
 * Fetches task fields from the API.
 *
 * @return {Promise<Record<string, TaskField>>} A promise that resolves to a record of task fields.
 * @throws Will throw an error if the API request fails.
 */
export async function fetchTaskFields(): Promise<Record<string, TaskField>> {
	const response = await fetch(`${apiRoot}/${apiNamespace}/tasks/fields`, {
		method: 'GET',
		credentials: 'include',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
	});

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
		`${apiRoot}/${apiNamespace}/tasks/fields/${fieldId}/options/reorder`,
		{
			method: 'POST',
			credentials: 'include',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce,
			},
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
	return fetch(`${apiRoot}/orders?search=${encodeURIComponent(term)}`, {
		method: 'GET',
		credentials: 'include',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
		signal,
	}).then(handleResponse<any[]>);
};
