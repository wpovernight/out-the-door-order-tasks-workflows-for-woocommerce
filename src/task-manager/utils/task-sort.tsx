import { FieldOption, Task } from '@shared/types/task';

export function groupAndSortTasks(
	tasks: Task[],
	statuses: FieldOption[]
): Record<string, Task[]> {
	if (tasks.length === 0) {
		return {};
	}

	const grouped: Record<string, typeof tasks> = {};

	// Index statuses by ID once so the per-task lookup below is O(1) instead of O(n).
	const slugByOptionId = new Map<number, string>();
	statuses.forEach((col) => {
		grouped[col.slug] = [];
		slugByOptionId.set(col.id, col.slug);
	});

	// task.status holds the status option's ID. Translate to slug to land in the right bucket.
	tasks.forEach((task) => {
		const columnSlug = slugByOptionId.get(task.status);
		if (columnSlug && grouped[columnSlug]) {
			grouped[columnSlug].push(task);
		}
	});

	// Sort tasks within each column by 'position'.
	for (const column in grouped) {
		grouped[column].sort((a, b) => a.position - b.position);
	}

	return grouped;
}
