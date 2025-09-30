export type FieldValue = {
	raw: any;
	resolved: any;
};

export type TaskField = {
	id: number;
	label: string;
	type: string;
	slug: string;
	is_required: boolean;
	is_editable: boolean;
	is_protected: boolean;
	values: FieldValue[];
};

export type Task = {
	id: number;
	title: string;
	description: string;
	created_at: string;
	updated_at: string;
	fields: TaskField[];

	// Derived properties for Kanban
	column: string;
	position: number;
};
