import * as React from 'react';
import {fetchTasks, updateTask} from '../../api/tasks';
import {Task} from '../../types/task';
import Column from './Column';

const KanbanBoard: React.FC = () => {
	const [tasks, setTasks] = React.useState<Task[]>([]);
	const [dropIndicator, setDropIndicator] = React.useState<{ columnId: string; index: number; } | null>(null);

	React.useEffect(() => {
		fetchTasks().then(setTasks).catch(console.error);
	}, []);

	const handleTaskDrop = (taskId: number, newColumn: string, newIndex: number) => {
		setTasks((currentState) => {
			const movedTask = currentState.find((task) => task.id === taskId);
			if (!movedTask) {
				return currentState;
			}

			const stateWithoutMovedTask = currentState.filter((task) => task.id !== taskId);

			if (newColumn === movedTask.column) {
				// Reordering within the same column
				const columnTasks = stateWithoutMovedTask.filter(task => task.column === newColumn);

				const safeIndex = Math.max(0, Math.min(newIndex, columnTasks.length));
				const reordered = [
					...columnTasks.slice(0, safeIndex),
					{...movedTask, position: safeIndex},
					...columnTasks.slice(safeIndex),
				].map((task, index) => ({...task, position: index}));

				const otherTasks = stateWithoutMovedTask.filter(task => task.column !== newColumn);

				return [...otherTasks, ...reordered];
			} else {
				// Moving across columns
				const sourceColumnTasks = stateWithoutMovedTask.filter(task => task.column === movedTask.column);
				const destinationColumnTasks = stateWithoutMovedTask.filter(task => task.column === newColumn);
				const unchangedTasks = stateWithoutMovedTask.filter(
					task => task.column !== movedTask.column && task.column !== newColumn
				);

				const safeIndex = Math.max(0, Math.min(newIndex, destinationColumnTasks.length));
				const updatedDestination = [
					...destinationColumnTasks.slice(0, safeIndex),
					{...movedTask, column: newColumn},
					...destinationColumnTasks.slice(safeIndex),
				].map((task, index) => ({...task, position: index}));

				const updatedSource = sourceColumnTasks.map((task, index) => ({...task, position: index}));

				return [...unchangedTasks, ...updatedSource, ...updatedDestination];
			}
		});

		// ToDo: Update backend
		// Persist to backend
		// updateTask(taskId, {
		// 	fields: [
		// 		{ slug: 'status', value: { raw: newColumn } },
		// 		{ slug: 'position', value: { raw: newIndex } },
		// 	],
		// });
	};

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
					dropIndicator={dropIndicator}
					setDropIndicator={setDropIndicator}
				/>
			))}
		</div>
	);
};

export default KanbanBoard;
