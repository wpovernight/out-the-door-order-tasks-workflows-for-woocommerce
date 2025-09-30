import React from 'react';
import {dropTargetForElements} from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import TaskCard from './Card';
import {Task} from '../../types/task';


type Props = {
	id: string;
	title: string;
	tasks: Task[];
	onTaskDrop: (taskId: number, newColumn: string, newIndex: number) => void;
	dropIndicator: { columnId: string; index: number } | null;
	setDropIndicator: React.Dispatch<React.SetStateAction<{ columnId: string; index: number } | null>>;
};

const Column: React.FC<Props> = ({id, title, tasks, onTaskDrop, dropIndicator, setDropIndicator}) => {
	const ref = React.useRef<HTMLDivElement>(null);

	React.useEffect(() => {
		const element = ref.current;
		if (!element || tasks.length > 0) {
			return;
		}

		return dropTargetForElements({
			element: element,
			getData: () => ({columnId: id, index: tasks.length}),
			onDragEnter: () => setDropIndicator({columnId: id, index: tasks.length}),
			onDragLeave: () => setDropIndicator(null),
			onDrop: ({source}) => {
				console.log('Dropped on column end');

				const taskId = source.data.taskId as number;
				onTaskDrop(taskId, id, tasks.length);
				setDropIndicator(null);
			},
		});
	}, [id, tasks.length, onTaskDrop, setDropIndicator]);

	return (
		<div ref={ref} className="kanban-column">
			<div className="header">
				<h2>{title}</h2>
				{/*	Add icon */}
			</div>

			{tasks.map((task, index) => (
				<React.Fragment key={task.id}>
					{dropIndicator?.columnId === id && dropIndicator.index === index && (
						<div className="drop-line"/>
					)}

					<TaskCard
						id={task.id}
						title={task.title}
						columnId={id}
						index={index}
						onTaskDrop={onTaskDrop}
						setDropIndicator={setDropIndicator}
					/>

				</React.Fragment>
			))}

			{/* Drop line at end of column */}
			{dropIndicator?.columnId === id && dropIndicator.index === tasks.length && (
				<div className="drop-line"/>
			)}
		</div>
	);
};

export default Column;
