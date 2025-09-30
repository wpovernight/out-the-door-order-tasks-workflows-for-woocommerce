import * as React from 'react';
import {fetchTasks, updateTask} from '../../api/tasks';
import {Task} from '../../types/task';
import Column from './Column';

const KanbanBoard: React.FC = () => {
	const [tasks, setTasks] = React.useState<Task[]>([]);

	React.useEffect(() => {
		fetchTasks().then(setTasks).catch(console.error);
	}, []);

	const handleTaskDrop = (taskId: number, newColumn: string, newIndex: number) => {
		setTasks((prev) => {
			// find the task
			const task = prev.find((t) => t.id === taskId);
			if (!task) return prev;

			// remove from old column
			const without = prev.filter((t) => t.id !== taskId);

			// insert at newIndex in new column
			const before = without.filter(t => t.column === newColumn);
			const after = without.filter(t => t.column !== newColumn);

			const updatedColumnTasks = [
				...before.slice(0, newIndex),
				{ ...task, column: newColumn, position: newIndex },
				...before.slice(newIndex).map((t, i) => ({ ...t, position: newIndex + 1 + i })),
			];

			return [...after, ...updatedColumnTasks];
		});

		// Persist to backend
		// updateTask(taskId, {
		// 	fields: [
		// 		{ slug: 'status', value: { raw: newColumn } },
		// 		{ slug: 'position', value: { raw: newIndex } },
		// 	],
		// });
	};


	console.log( "Tasks:" );
	console.log( tasks );

	const columns = ['To Do', 'In Progress', 'Completed']; // ToDo: fetch from backend

	return (
		<div className="kanban-board">
			{columns.map((col) => (
				<Column
					key={col}
					id={col}
					title={col.toUpperCase()}
					tasks={
						tasks
							.filter((t) => t.column === col)
							.sort((a, b) => a.position - b.position)
					}
					onTaskDrop={handleTaskDrop}
				/>
			))}
		</div>
	);
};

export default KanbanBoard;
