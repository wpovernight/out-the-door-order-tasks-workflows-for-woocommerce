// views/Kanban/data.ts
import {Task} from "../../types/task";

// ------------------------------
// Types
// ------------------------------

export type DraggingCardData = {
    type: "card";
    task: Task;
    fromColumn: string;
    rect: DOMRect;
};

export type DraggingColumnData = {
    type: "column";
    column: string;
};

export type CardDropTargetData = {
    type: "card-drop-target";
    task: Task;
    column: string;
    edge?: "top" | "bottom";
};

export type ColumnDropTargetData = {
    type: "column-drop-target";
    column: string;
};

// ------------------------------
// Type Guards
// ------------------------------

export function isCardData(data: any): data is DraggingCardData {
    return data?.type === "card" && !!data.task;
}

export function isColumnData(data: any): data is DraggingColumnData {
    return data?.type === "column" && typeof data.column === "string";
}

export function isCardDropTargetData(data: any): data is CardDropTargetData {
    return data?.type === "card-drop-target" && !!data.task;
}

export function isColumnDropTargetData(data: any): data is ColumnDropTargetData {
    return data?.type === "column-drop-target" && typeof data.column === "string";
}

// ------------------------------
// Card helpers
// ------------------------------

export function getCardData({
                                task,
                                fromColumn,
                                rect,
                            }: {
    task: Task;
    fromColumn: string;
    rect: DOMRect;
}): DraggingCardData {
    return {
        type: "card",
        task,
        fromColumn,
        rect,
    };
}

export function getCardDropTargetData({task, column}: { task: Task; column: string; }): CardDropTargetData {
    return {
        type: "card-drop-target",
        task,
        column,
    };
}

// ------------------------------
// Column helpers
// ------------------------------

export function getColumnData({column}: { column: string; }): DraggingColumnData {
    return {
        type: "column",
        column,
    };
}

export function getColumnDropTargetData({column}: { column: string; }): ColumnDropTargetData {
    return {
        type: "column-drop-target",
        column,
    };
}
