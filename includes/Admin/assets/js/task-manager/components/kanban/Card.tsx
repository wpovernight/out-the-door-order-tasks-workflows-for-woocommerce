import React from 'react';
import {draggable, dropTargetForElements} from '@atlaskit/pragmatic-drag-and-drop/element/adapter';

type Props = {
	id: number;
	title: string;
	columnId: string;
	index: number;
	onTaskDrop: (taskId: number, newColumn: string, newIndex: number) => void;
	setDropIndicator: React.Dispatch<React.SetStateAction<{ columnId: string; index: number } | null>>;
};

const TaskCard: React.FC<Props> = ({id, title, columnId, index, onTaskDrop, setDropIndicator}) => {
	const ref = React.useRef<HTMLDivElement>(null);

	React.useEffect(() => {
		const element = ref.current;
		if (!element) {
			return;
		}

		// Make draggable
		const cleanupDrag = draggable({
			element: element,
			getInitialData: () => ({taskId: id}),
		});

		// Make droppable → insert before this card
		const cleanupDrop = dropTargetForElements({
			element: element,
			getData: () => ({columnId, index}),
			onDragEnter: () => setDropIndicator({columnId, index}),
			onDragLeave: () => setDropIndicator(null),
			onDrop: ({source}) => {
				console.log('Dropped on card', id, 'at index', index);

				const taskId = source.data.taskId as number;
				onTaskDrop(taskId, columnId, index);
				setDropIndicator(null);
			},
		});

		return () => {
			cleanupDrag();
			cleanupDrop();
		};
	}, [id, columnId, index, onTaskDrop, setDropIndicator]);

	return (
		<div ref={ref} className="kanban-card">
			{title}
		</div>
	);
};

export default TaskCard;
