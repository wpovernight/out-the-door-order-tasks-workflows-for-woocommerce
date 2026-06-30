import { StatusRoles } from '@shared/types/task';
import {
	getApiRoot,
	getApiNamespace,
	getHeaders,
	handleResponse,
} from './client';

export async function updateStatusRoles(
	updates: Partial<StatusRoles>
): Promise<StatusRoles> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/tasks/status-roles`,
		{
			method: 'PUT',
			credentials: 'include',
			headers: getHeaders(),
			body: JSON.stringify(updates),
		}
	);

	return handleResponse<StatusRoles>(response);
}
