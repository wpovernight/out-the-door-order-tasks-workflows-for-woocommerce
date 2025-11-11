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
	const { statuses, moveTask } = useTasks();
	const { viewTasks, setViewTasks, clearSelectedTask } = useViewTasks();
	const scrollableRef = useRef<HTMLDivElement | null>(null);

	const statusesRef = useRef(statuses);
	useEffect(() => {
		statusesRef.current = statuses;
	}, [statuses]);

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

							// Persist the move via API
							const previousTaskId =
								insertAt > 0 ? toList[insertAt - 1].id : null;
							const targetStatusId =
								statusesRef.current.find(
									(s) => s.slug === toColumn
								)?.id || 0;
							moveTask(task.id, previousTaskId, targetStatusId);

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

							// Persist the move via API
							// Get the last task in the target column to set as previousTaskId
							// - 2 is used because we just pushed the task to the end of the list
							const previousTaskId =
								toList.length > 1
									? toList[toList.length - 2].id
									: null;
							const targetStatusId =
								statusesRef.current.find(
									(s) => s.slug === toColumn
								)?.id || 0;
							moveTask(task.id, previousTaskId, targetStatusId);

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
	}, [setViewTasks, moveTask, statusesRef]);

	// Clear highlight when clicking anywhere on the board background
	const handleClick = () => {
		clearSelectedTask();
	};

	return (
		// eslint-disable-next-line jsx-a11y/no-static-element-interactions
		<div
			ref={scrollableRef}
			className="kanban-board"
			onClick={handleClick}
			tabIndex={0}
			onKeyDown={(e) => {
				if (e.key === 'Escape') {
					clearSelectedTask();
				}
			}}
		>
			{statusesRef.current.map((col) => (
				<Column
					key={col.id}
					column={col}
					tasks={viewTasks[col.slug] || []}
				/>
			))}
		</div>
	);
};
