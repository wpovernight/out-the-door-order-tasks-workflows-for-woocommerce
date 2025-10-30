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

export const Board: React.FC = () => {
	const { tasks, statuses } = useTasks();
	const scrollableRef = useRef<HTMLDivElement | null>(null);
	const { setTasks } = useTasks();

	// Group tasks by status(column) name
	const taskGroups = useMemo(() => {
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
		const sortByPreviousTaskId = (tasksInColumn: typeof tasks) => {
			const byId = new Map(tasksInColumn.map((t) => [t.id, t]));
			const sorted: typeof tasks = [];
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
						.find((t) => t.previous_task_id === task.id);
					task = current;
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
	}, [tasks, statuses]);

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
								column: toColumn,
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

						setTasks((prev) => {
							return prev.map((t) =>
								t.id === task.id
									? { ...t, column: toColumn }
									: t
							);
						});

						console.log(tasks); // ToDo: Remove debug log
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
