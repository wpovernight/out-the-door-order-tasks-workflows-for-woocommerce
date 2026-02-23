import { FieldResolved, Task } from '@shared/types/task';

export const getFieldBySlug = (task: Task, slug: string) => {
	return task.fields?.find((field) => field.slug === slug);
};

export const getFieldValue = (
	task: Task,
	slug: string,
	property: string | null = null,
	defaultValue: FieldResolved | null = null
): FieldResolved | null => {
	const field = getFieldBySlug(task, slug);
	if (!field || !field.values || field.values.length === 0) {
		return null;
	}

	const resolved = field.values[0].resolved;

	if (property !== null) {
		if (
			resolved !== null &&
			typeof resolved === 'object' &&
			!Array.isArray(resolved)
		) {
			return (
				((resolved as Record<string, unknown>)[
					property
				] as FieldResolved) ?? defaultValue
			);
		}
		return defaultValue;
	}

	return resolved;
};

export const getFieldRawValues = (
	task: Task,
	slug: string
): (string | number | boolean)[] | null => {
	const field = getFieldBySlug(task, slug);
	if (!field || !field.values || field.values.length === 0) {
		return null;
	}

	return field.values
		.map((value) => value.raw)
		.filter(
			(raw): raw is string | number | boolean =>
				raw !== null && raw !== undefined
		);
};

export const getFieldRawValue = (
	task: Task,
	slug: string
): string | number | boolean | null => {
	return getFieldRawValues(task, slug)?.[0] ?? null;
};

export const getFieldObjectValue = (
	task: Task,
	slug: string
): Record<string, unknown> | null => {
	const fieldValue = getFieldValue(task, slug);

	if (
		!fieldValue ||
		typeof fieldValue !== 'object' ||
		Array.isArray(fieldValue)
	) {
		return null;
	}

	return fieldValue as Record<string, unknown>;
};

export const getTaskDateField = (
	task: Task,
	fieldSlug: string
): Date | null => {
	const dateValue = getFieldRawValue(task, fieldSlug);
	if (!dateValue || typeof dateValue !== 'string') {
		return null;
	}

	const date = new Date(dateValue);
	return isNaN(date.getTime()) ? null : date;
};

/**
 * Updates a task's field values and corresponding top-level properties. (only in local)
 *
 * @param task    - The task to update
 * @param updates - Record of field slugs to their new values (raw and resolved)
 * @return A new task object with updated fields
 *
 * @example
 * updateTaskFields(task, {
 *   status: { raw: 1, resolved: statusOption },
 *   position: { raw: 0.5, resolved: null }
 * })
 */
export const updateTaskFields = (
	task: Task,
	updates: Record<string, { raw: any; resolved: any }>
): Task => {
	const updatedFields = task.fields?.map((field) => {
		if (updates[field.slug]) {
			return {
				...field,
				values: [updates[field.slug]],
			};
		}
		return field;
	});

	// Extract top-level properties from the updates
	const topLevelUpdates: Partial<Task> = {};
	if (updates.status) {
		topLevelUpdates.status =
			typeof updates.status.resolved === 'object'
				? updates.status.resolved.slug
				: updates.status.resolved;
	}
	if (updates.position) {
		topLevelUpdates.position = updates.position.raw;
	}

	return {
		...task,
		...topLevelUpdates,
		fields: updatedFields,
	};
};

export const isTaskArchived = (task: Task): boolean => {
	return getFieldRawValue(task, 'archived_date') !== null;
};
