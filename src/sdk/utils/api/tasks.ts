import { Task } from '@sdk/types/task';
import {
	getApiRoot,
	getApiNamespace,
	getHeaders,
	handleEnvelope,
} from './client';

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

	const data = await handleEnvelope<any[]>(response);

	return data.map((task: any) => {
		const statusField = task.fields.find(
			(field: any) => field.slug === 'status'
		);
		const positionField = task.fields.find(
			(field: any) => field.slug === 'position'
		);

		return {
			...task,
			status: statusField?.values?.[0]?.raw,
			position: positionField?.values?.[0]?.raw,
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

	const task = await handleEnvelope<any>(response);

	// Apply the same mapping as fetchTasks
	const statusField = task.fields?.find(
		(field: any) => field.slug === 'status'
	);
	const positionField = task.fields?.find(
		(field: any) => field.slug === 'position'
	);

	return {
		...task,
		status: statusField?.values?.[0]?.raw,
		position: positionField?.values?.[0]?.raw,
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

	const task = await handleEnvelope<any>(response);

	// Apply the same mapping as fetchTasks
	const statusField = task.fields?.find(
		(field: any) => field.slug === 'status'
	);
	const positionField = task.fields?.find(
		(field: any) => field.slug === 'position'
	);

	return {
		...task,
		status: statusField?.values?.[0]?.raw,
		position: positionField?.values?.[0]?.raw,
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

	return handleEnvelope<void>(response);
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

	return handleEnvelope<{ new_position: number }>(response);
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

	const data = await handleEnvelope<{ success: boolean }>(response);
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

	const data = await handleEnvelope<{ success: boolean }>(response);
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

	const data = await handleEnvelope<{ success: boolean }>(response);
	return data.success;
}
