import { FieldOption } from '@shared/types/task';
import {
	getApiRoot,
	getApiNamespace,
	getHeaders,
	handleEnvelope,
} from './client';

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

	return handleEnvelope<FieldOption[]>(response);
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

	return handleEnvelope<{ success: boolean; message: string }>(response);
}

export async function createFieldOption(
	fieldId: number,
	payload: Partial<FieldOption>
): Promise<FieldOption> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/tasks/fields/${fieldId}/options`,
		{
			method: 'POST',
			credentials: 'include',
			headers: getHeaders(),
			body: JSON.stringify(payload),
		}
	);

	return handleEnvelope<FieldOption>(response);
}

export async function updateFieldOption(
	fieldId: number,
	optionId: number,
	payload: Partial<FieldOption>
): Promise<FieldOption> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/tasks/fields/${fieldId}/options/${optionId}`,
		{
			method: 'PUT',
			credentials: 'include',
			headers: getHeaders(),
			body: JSON.stringify(payload),
		}
	);

	return handleEnvelope<FieldOption>(response);
}

export async function deleteFieldOption(
	fieldId: number,
	optionId: number
): Promise<void> {
	const response = await fetch(
		`${getApiRoot()}/${getApiNamespace()}/tasks/fields/${fieldId}/options/${optionId}`,
		{
			method: 'DELETE',
			credentials: 'include',
			headers: getHeaders(),
		}
	);

	return handleEnvelope<void>(response);
}
