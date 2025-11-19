export type FieldOption = {
	id: number;
	field_id: number;
	slug: string;
	label: string;
	color: `#${string}` | `rgb(${number},${number},${number})` | string;
};

type FieldPrimitive = string | number | boolean | null;
export type FieldResolved =
	| FieldPrimitive
	| FieldPrimitive[]
	| FieldOption
	| FieldOption[];

export type FieldValue = {
	raw: FieldPrimitive | FieldPrimitive[];
	resolved: FieldResolved;
};

export function isFieldOption(value: FieldResolved): value is FieldOption {
	return (
		typeof value === 'object' &&
		value !== null &&
		!Array.isArray(value) &&
		'field_id' in value
	);
}

type FieldType = 'text' | 'number' | 'select' | 'date' | string; // fallback for custom extensions

export type TaskField = {
	id: number;
	label: string;
	type: FieldType;
	slug: string;
	is_required: boolean;
	is_editable: boolean;
	is_protected: boolean;
	values?: FieldValue[];
};

export type Task = {
	id: number;
	title: string;
	description: string;
	created_at: string;
	updated_at: string;
	fields: TaskField[];

	// Derived UI properties for Kanban
	status: string;
	position: number;
	previous_task_id?: number | null;
};
