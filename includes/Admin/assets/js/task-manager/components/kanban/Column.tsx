import * as React from 'react';
import { dropTargetForElements } from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import TaskCard from './Card';
import { Task } from '../../types/task';


type Props = {
	id: string;
	title: string;
	tasks: Task[];
	onTaskDrop: (taskId: number, newColumn: string, newIndex: number) => void;
};

const Column: React.FC<Props> = ({ id, title, tasks, onTaskDrop }) => {
	const ref = React.useRef<HTMLDivElement>(null);

	React.useEffect(() => {
		const element = ref.current;
		if (!element) return;

		return dropTargetForElements({
			element: element,
			getData: () => ({ columnId: id }), // columnId is string
			onDrop: ({ source }) => {
				const taskId = source.data.taskId as number;
				onTaskDrop(taskId, id, 0);
			},
		});
	}, [id, onTaskDrop]);

	return (
		<div ref={ref} className="column">
			<div className="header">
				<h2>{title}</h2>
			{/*	Add icon */}

			</div>
			{tasks.map((task) => (
				<TaskCard key={task.id} id={task.id} title={task.title} />
			))}
		</div>
	);
};

export default Column;
