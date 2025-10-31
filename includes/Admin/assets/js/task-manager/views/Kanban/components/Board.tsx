import React, { useEffect, useRef } from 'react';

import invariant from 'tiny-invariant';
import { monitorForElements } from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import { combine } from '@atlaskit/pragmatic-drag-and-drop/combine';
import { autoScrollForElements } from '@atlaskit/pragmatic-drag-and-drop-auto-scroll/element';
import { extractClosestEdge } from '@atlaskit/pragmatic-drag-and-drop-hitbox/closest-edge';

import { useTasks } from '../../../context/TaskContext';
import { isCardData, isColumnData, isCardDropTargetData } from '../data';
import { Column } from './Column';
import { useViewTasks } from '../context/ViewTaskContext';

export const Board: React.FC = () => {
	const { statuses } = useTasks();
	const { viewTasks, setViewTasks } = useViewTasks();
	const scrollableRef = useRef<HTMLDivElement | null>(null);

	// Setup DND behavior
	useEffect(() => {
		const scrollable = scrollableRef.current;
		invariant(scrollable);

		return combine(
			monitorForElements({
				canMonitor: ({ source }) => isCardData(source.data),
				async onDrop({ source, location }) {
					const dragging = source.data;
					if (!isCardData(dragging)) {
						return;
					}

					const destination = location.current.dropTargets[0];
					if (!destination) {
						return;
					}

					const dropTargetData = destination.data;
					const fromColumn = dragging.fromColumn;
					const task = dragging.task;

					// Drop onto another card
					if (isCardDropTargetData(dropTargetData)) {
						const toColumn = dropTargetData.column;
						const edge = extractClosestEdge(dropTargetData);
						const targetTask = dropTargetData.task;

						if (task.id === targetTask.id) {
							return;
						}

						setViewTasks((prev) => {
							const updated = structuredClone(prev);
							const fromList = updated[fromColumn] || [];
							const toList = updated[toColumn] || [];

							// Remove from source
							const fromIndex = fromList.findIndex(
								(t) => t.id === task.id
							);
							if (fromIndex !== -1) {
								fromList.splice(fromIndex, 1);
							}

							// Find target index and insert
							const targetIndex = toList.findIndex(
								(t) => t.id === targetTask.id
							);
							const insertAt =
								edge === 'bottom'
									? targetIndex + 1
									: targetIndex;
							toList.splice(insertAt, 0, {
								...task,
								status: toColumn,
							});

							updated[fromColumn] = fromList;
							updated[toColumn] = toList;
							return updated;
						});
						return;
					}

					// Drop on column background
					if (isColumnData(dropTargetData)) {
						const toColumn = dropTargetData.column;
						if (fromColumn === toColumn) {
							return;
						}

						setViewTasks((prev) => {
							const updated = structuredClone(prev);
							const fromList = updated[fromColumn] || [];
							const toList = updated[toColumn] || [];

							// Remove from source column
							const fromIndex = fromList.findIndex(
								(t) => t.id === task.id
							);
							if (fromIndex !== -1) {
								fromList.splice(fromIndex, 1);
							}

							// Append to end of target column
							toList.push({ ...task, status: toColumn });

							updated[fromColumn] = fromList;
							updated[toColumn] = toList;
							return updated;
						});
					}
				},
			}),
			autoScrollForElements({
				element: scrollable,
				canScroll: ({ source }) => isCardData(source.data),
			})
		);
	}, [setViewTasks]);

	return (
		<div ref={scrollableRef} className="kanban-board">
			{statuses.map((col) => (
				<Column
					key={col.id}
					column={col}
					tasks={viewTasks[col.label] || []}
				/>
			))}
		</div>
	);
};
