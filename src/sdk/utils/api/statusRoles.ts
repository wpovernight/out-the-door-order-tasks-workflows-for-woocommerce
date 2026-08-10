import { StatusRoles } from '@sdk/types/task';
import {
	getApiRoot,
	getApiNamespace,
	getHeaders,
	handleEnvelope,
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

	return handleEnvelope<StatusRoles>(response);
}
