// views/Kanban/data.ts
import { Task } from '@sdk/types/task';

// ------------------------------
// Drag Data Types
// ------------------------------

export type DraggingCardData = Readonly<{
	type: 'card';
	task: Task;
	fromColumn: string;
	rect: DOMRect;
}>;

export type DraggingColumnData = Readonly<{
	type: 'column';
	column: string;
}>;

export type CardDropTargetData = Readonly<{
	type: 'card-drop-target';
	task: Task;
	column: string;
	edge?: 'top' | 'bottom';
}>;

export type ColumnDropTargetData = Readonly<{
	type: 'column-drop-target';
	column: string;
}>;

// ------------------------------
// Type Guards
// ------------------------------

export function isTask(data: unknown): data is Task {
	return (
		typeof data === 'object' &&
		data !== null &&
		'id' in data &&
		typeof (data as { id: unknown }).id === 'number'
	);
}

export function isCardData(data: unknown): data is DraggingCardData {
	return (
		typeof data === 'object' &&
		data !== null &&
		(data as any).type === 'card' &&
		isTask((data as any).task) &&
		typeof (data as any).fromColumn === 'string'
	);
}

export function isColumnData(data: unknown): data is DraggingColumnData {
	return (
		typeof data === 'object' &&
		data !== null &&
		(data as any).type === 'column' &&
		typeof (data as any).column === 'string'
	);
}

export function isCardDropTargetData(
	data: unknown
): data is CardDropTargetData {
	return (
		typeof data === 'object' &&
		data !== null &&
		(data as any).type === 'card-drop-target' &&
		isTask((data as any).task) &&
		typeof (data as any).column === 'string'
	);
}

export function isColumnDropTargetData(
	data: unknown
): data is ColumnDropTargetData {
	return (
		typeof data === 'object' &&
		data !== null &&
		(data as any).type === 'column-drop-target' &&
		typeof (data as any).column === 'string'
	);
}

// ------------------------------
// Helper Factory
// ------------------------------

function freeze<T extends object>(data: T): Readonly<T> {
	return Object.freeze(data);
}

// ------------------------------
// Card Helpers
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
	return freeze({
		type: 'card',
		task,
		fromColumn,
		rect,
	});
}

export function getCardDropTargetData({
	task,
	column,
}: {
	task: Task;
	column: string;
}): CardDropTargetData {
	return freeze({
		type: 'card-drop-target',
		task,
		column,
	});
}

// ------------------------------
// Column Helpers
// ------------------------------

export function getColumnData({
	column,
}: {
	column: string;
}): DraggingColumnData {
	return freeze({
		type: 'column',
		column,
	});
}

export function getColumnDropTargetData({
	column,
}: {
	column: string;
}): ColumnDropTargetData {
	return freeze({
		type: 'column-drop-target',
		column,
	});
}
