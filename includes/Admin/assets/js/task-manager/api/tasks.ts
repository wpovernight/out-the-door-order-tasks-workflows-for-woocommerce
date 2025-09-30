import {Task} from "../types/task";

export async function fetchTasks(): Promise<Task[]> {
	const response = await fetch(
		(window as any).WPO_AOM_TaskManager.apiRoot + '/tasks',
		{
			method: 'GET',
			credentials: 'include',
			headers: {
				'X-WP-Nonce': (window as any).WPO_AOM_TaskManager.nonce,
			},
		}
	);

	if (!response.ok) {
		const errorText = await response.text();
		throw new Error(`Failed to fetch tasks: ${response.status} ${errorText}`);
	}

	const data = await response.json();

	return data.map((task: any) => {
		const statusField = task.fields.find((field: any) => field.slug === 'status');
		const positionField = task.fields.find((field: any) => field.slug === 'position');
		return {
			...task,
			column: statusField?.value?.raw,
			position: positionField?.value?.raw,
		};
	});
}


export async function updateTask(taskId: number, newStatus: object) {
	// await fetch(`/api/tasks/${taskId}`, {
	// 	method: 'PATCH',
	// 	headers: { 'Content-Type': 'application/json' },
	// 	body: JSON.stringify({
	// 		fields: [
	// 			{
	// 				slug: 'status',
	// 				value: { raw: newStatus },
	// 			},
	// 		],
	// 	}),
	// });
}

