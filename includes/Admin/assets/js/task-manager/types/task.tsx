export type FieldValue = {
    raw: unknown;
    resolved: unknown;
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

    // Derived UI properties for Kanban
    column: string;
    position: number;
};

export type FieldOption = {
    id: number;
    field_id: number;
    label: string;
    color: string;
};