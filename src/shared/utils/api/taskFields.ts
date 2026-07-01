import { TaskField } from '@shared/types/task';
import {
	getApiRoot,
	getApiNamespace,
	getHeaders,
	handleEnvelope,
} from './client';

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

	return handleEnvelope<Record<string, TaskField>>(response);
}
