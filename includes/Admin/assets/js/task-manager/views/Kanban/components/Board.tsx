import React, { useEffect, useMemo, useRef } from 'react';
import invariant from 'tiny-invariant';
import { monitorForElements } from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import { combine } from '@atlaskit/pragmatic-drag-and-drop/combine';
import { autoScrollForElements } from '@atlaskit/pragmatic-drag-and-drop-auto-scroll/element';
import { extractClosestEdge } from '@atlaskit/pragmatic-drag-and-drop-hitbox/closest-edge';

import { useTasks } from '../../../context/TaskContext';
import {
	isCardData,
	isColumnData,
	isCardDropTargetData,
	isTask,
} from '../data';
import { Column } from './Column';
import { groupAndSortTasks } from '../../../utils/task-sort';

export const Board: React.FC = () => {
	const { tasks, statuses, setTasks } = useTasks();
	const scrollableRef = useRef<HTMLDivElement | null>(null);

	// Group tasks by status(column) name
	const taskGroups = useMemo(
		() => groupAndSortTasks(tasks, statuses),
		[tasks, statuses]
	);

	// Enable horizontal auto-scroll while dragging.
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
						// if dropped outside any drop targets
						return;
					}

					const dropTargetData = destination.data;

					const fromColumn = dragging.fromColumn;
					const task = dragging.task;

					// Drop on another card
					if (isCardDropTargetData(dropTargetData)) {
						const toColumn = dropTargetData.column;
						const edge = extractClosestEdge(dropTargetData);
						const targetTask = dropTargetData.task;

						// Dropping onto itself - no-op
						if (task.id === targetTask.id) {
							return;
						}

						// Reorder locally
						setTasks((prev) => {
							const newTasks = [...prev];

							// Remove task from source column
							const fromIndex = newTasks.findIndex(
								(t) => t.id === task.id
							);
							if (fromIndex === -1) {
								return prev;
							}
							newTasks.splice(fromIndex, 1);

							// Find insertion point in target column
							const targetTaskIndex = newTasks.findIndex(
								(t) => t.id === targetTask.id
							);
							if (targetTaskIndex === -1) {
								return prev;
							}

							const insertAt =
								edge === 'bottom'
									? targetTaskIndex + 1
									: targetTaskIndex;

							// Insert task at new position with updated column
							newTasks.splice(insertAt, 0, {
								...task,
								status: toColumn,
							});

							return newTasks;
						});

						if (fromColumn !== dropTargetData.column) {
							// ToDo: Complete this
							// await saveTask(task.id, {column: dropTargetData.column});
						}

						return;
					}

					// Drop on column background
					if (isColumnData(dropTargetData)) {
						const toColumn = dropTargetData.column;
						if (fromColumn === toColumn) {
							return;
						}
						console.log(
							`Moving task ${task.id} from ${fromColumn} to ${toColumn}`
						); // ToDo: Remove debug log

						const lastInColumn = taskGroups[toColumn]?.slice(-1)[0];

						setTasks((prev) => {
							return prev.map((t) =>
								t.id === task.id
									? {
											...t,
											status: toColumn,
											previous_task_id: lastInColumn
												? lastInColumn?.id
												: null,
										}
									: t
							);
						});

						// ToDo: update the previous_task_id of the next task in the original column

						// console.log(tasks); // ToDo: Remove debug log
						// await saveTask(task.id, {column: toColumn}); // ToDo: Complete this
					}
				},
			}),
			autoScrollForElements({
				element: scrollable,
				canScroll: ({ source }) => isCardData(source.data),
			})
		);
	}, [setTasks]);

	return (
		<div ref={scrollableRef} className="kanban-board">
			{statuses.map((col) => (
				<Column
					key={col.id}
					column={col}
					tasks={taskGroups[col.label] || []}
				/>
			))}
		</div>
	);
};
