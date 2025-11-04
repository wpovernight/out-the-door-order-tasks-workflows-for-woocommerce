import { FieldOption, Task } from '../types/task';

export function groupAndSortTasks(
	tasks: Task[],
	statuses: FieldOption[]
): Record<string, Task[]> {
	if (tasks.length === 0) {
		return {};
	}

	const grouped: Record<string, typeof tasks> = {};

	statuses.forEach((col) => {
		grouped[col.slug] = [];
	});

	// Distribute tasks into their respective columns
	tasks.forEach((task) => {
		const columnName = task.status;
		if (grouped[columnName]) {
			grouped[columnName].push(task);
		}
	});

	// Sort tasks within each column by 'position'.
	for (const column in grouped) {
		grouped[column].sort((a, b) => a.position - b.position);
	}

	return grouped;
}
