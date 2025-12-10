import { FieldResolved, Task } from '@shared/types/task';

export const getFieldBySlug = (task: Task, slug: string) => {
	return task.fields?.find((field) => field.slug === slug);
};

export const getFieldValue = (
	task: Task,
	slug: string
): FieldResolved | null => {
	const field = getFieldBySlug(task, slug);
	if (!field || !field.values || field.values.length === 0) {
		return null;
	}

	return field.values[0].resolved;
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

export const getTaskDueDate = (task: Task): Date | null => {
	const dueDateValue = getFieldRawValue(task, 'due_date');
	if (!dueDateValue || typeof dueDateValue !== 'string') {
		return null;
	}

	const date = new Date(dueDateValue);
	return isNaN(date.getTime()) ? null : date;
};
