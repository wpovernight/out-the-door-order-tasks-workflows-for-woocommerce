import { FieldOption, Task, TaskArray } from '../types/task';
import { isTask } from '../views/Kanban/data';

export function groupAndSortTasks(
	tasks: Task[],
	statuses: FieldOption[]
): Record<string, Task[]> {
	console.log('Grouping tasks by status...'); // ToDo: Remove debug log
	const grouped: Record<string, typeof tasks> = {};

	statuses.forEach((col) => {
		grouped[col.label] = [];
	});

	// Distribute tasks into their respective columns
	tasks.forEach((task) => {
		const columnName = task.status;
		if (grouped[columnName]) {
			grouped[columnName].push(task);
		}
	});

	// Order tasks by previous_task_id chain
	const sortByPreviousTaskId = (tasksInColumn: TaskArray): TaskArray => {
		const byId = new Map(tasksInColumn.map((t) => [t.id, t]));
		const sorted: TaskArray = [];
		const remaining = new Set(tasksInColumn.map((t) => t.id));

		// Find the first task(s) without a valid previous_task_id
		const heads = tasksInColumn.filter(
			(t) => !t.previous_task_id || !byId.has(t.previous_task_id)
		);

		// Start sorting by walking through linked previous_task_id chains
		const visitChain = (task: any) => {
			let current = task;
			while (current && remaining.has(current.id)) {
				sorted.push(current);
				remaining.delete(current.id);
				current = [...remaining]
					.map((id) => byId.get(id))
					.filter(isTask)
					.find((t) => t.previous_task_id === current.id);
			}
		};

		heads.sort((a, b) => a.position - b.position).forEach(visitChain);

		// If some tasks are still unlinked, sort them by position
		if (remaining.size > 0) {
			const unlinked = [...remaining]
				.map((id) => byId.get(id))
				.filter(isTask);
			unlinked
				.sort((a, b) => a.position - b.position)
				.forEach((t) => sorted.push(t));
		}

		return sorted;
	};

	// Sort tasks in each column
	for (const column in grouped) {
		const sorted = sortByPreviousTaskId(grouped[column]);

		// Reassign sorted list
		grouped[column] = sorted;

		// Ensure previous_task_id is filled consistently
		for (let index = 0; index < sorted.length; index++) {
			const current = sorted[index];
			const previous = sorted[index - 1];
			current.previous_task_id = previous ? previous.id : null;
		}
	}

	return grouped;
}
