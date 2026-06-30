import React, { useEffect, useRef, useState } from 'react';

import invariant from 'tiny-invariant';
import { monitorForElements } from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import { combine } from '@atlaskit/pragmatic-drag-and-drop/combine';
import { autoScrollForElements } from '@atlaskit/pragmatic-drag-and-drop-auto-scroll/element';
import { extractClosestEdge } from '@atlaskit/pragmatic-drag-and-drop-hitbox/closest-edge';
import { useTasks } from '@shared/context/TaskContext';
import {
	isCardData,
	isColumnData,
	isCardDropTargetData,
	isColumnDropTargetData,
} from '../data';
import { Column } from './Column';
import { DraftColumn } from './DraftColumn';
import { useViewTasks } from '../context/ViewTaskContext';

export const Board: React.FC = () => {
	const { fieldOptions, moveTask, reorderFieldOptions } = useTasks();
	const { viewTasks, setViewTasks, clearSelectedTask } = useViewTasks();
	const [draftColumn, setDraftColumn] = useState<{
		fieldId: number;
		position: number;
	} | null>(null);
	const orderedColumns = fieldOptions.status ?? [];

	const requestAddColumn = (fieldId: number, position: number) => {
		setDraftColumn({ fieldId, position });
	};

	const clearDraftColumn = () => setDraftColumn(null);

	const draftIndex = draftColumn
		? (() => {
				const index = orderedColumns.findIndex(
					(col) => col.position >= draftColumn.position
				);
				return index === -1 ? orderedColumns.length : index;
			})()
		: -1;

	const scrollableRef = useRef<HTMLDivElement | null>(null);

	// Mirror fieldOptions.status into a ref so the DnD effect can read the
	// latest columns inside its onDrop callback without depending on
	// fieldOptions in the deps array (which would tear down and re-register
	// the DnD setup on every field-options update).
	const statusesRef = useRef(fieldOptions.status || []);
	useEffect(() => {
		if (fieldOptions.status) {
			statusesRef.current = fieldOptions.status;
		}
	}, [fieldOptions.status]);

	// Setup DND behavior
	useEffect(() => {
		const scrollable = scrollableRef.current;
		invariant(scrollable);

		return combine(
			// Monitor for card drops
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

						let previousTaskId: number | null = null;
						const targetStatusId = statusesRef.current.find(
							(s) => s.slug === toColumn
						)?.id;
						if (!targetStatusId) {
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
								status: targetStatusId,
							});

							updated[fromColumn] = fromList;
							updated[toColumn] = toList;

							// Compute API parameters based on UPDATED state
							previousTaskId =
								insertAt > 0 ? toList[insertAt - 1].id : null;

							return updated;
						});

						// Defer API call to after render cycle to avoid React warnings
						queueMicrotask(() => {
							moveTask(task.id, previousTaskId, targetStatusId);
						});

						return;
					}

					// Drop on column background
					if (isColumnData(dropTargetData)) {
						const toColumn = dropTargetData.column;
						if (fromColumn === toColumn) {
							return;
						}

						let previousTaskId: number | null = null;
						const targetStatusId = statusesRef.current.find(
							(s) => s.slug === toColumn
						)?.id;
						if (!targetStatusId) {
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
							toList.push({ ...task, status: targetStatusId });

							updated[fromColumn] = fromList;
							updated[toColumn] = toList;

							// Compute API parameters based on UPDATED state
							// Get the last task in the target column to set as previousTaskId
							// - 2 is used because we just pushed the task to the end of the list
							previousTaskId =
								toList.length > 1
									? toList[toList.length - 2].id
									: null;

							return updated;
						});

						// Defer API call to after render cycle to avoid React warnings
						queueMicrotask(() => {
							moveTask(task.id, previousTaskId, targetStatusId);
						});
					}
				},
			}),

			// Monitor for column drops (reordering)
			monitorForElements({
				canMonitor: ({ source }) => isColumnData(source.data),
				onDrop({ source, location }) {
					const dragging = source.data;
					if (!isColumnData(dragging)) {
						return;
					}

					const destination = location.current.dropTargets[0];
					if (!destination) {
						return;
					}

					const dropTargetData = destination.data;

					if (isColumnDropTargetData(dropTargetData)) {
						const fromColumnSlug = dragging.column;
						const toColumnSlug = dropTargetData.column;

						if (fromColumnSlug === toColumnSlug) {
							return;
						}

						// Extract the edge to determine insert position
						const edge = extractClosestEdge(dropTargetData);

						// Compute the new column order from the latest snapshot
						// and dispatch through TaskContext. The context method
						// applies the optimistic update to global field
						// options and handles rollback on API failure.
						const currentColumns = statusesRef.current;
						const fromIndex = currentColumns.findIndex(
							(col) => col.slug === fromColumnSlug
						);
						const toIndex = currentColumns.findIndex(
							(col) => col.slug === toColumnSlug
						);

						if (fromIndex === -1 || toIndex === -1) {
							return;
						}

						const updated = [...currentColumns];
						const [movedColumn] = updated.splice(fromIndex, 1);
						const newToIndex = updated.findIndex(
							(col) => col.slug === toColumnSlug
						);
						const insertIndex =
							edge === 'right' ? newToIndex + 1 : newToIndex;
						updated.splice(insertIndex, 0, movedColumn);

						const orderedIds = updated.map((col) => col.id);
						const fieldId = updated[0]?.field_id;
						if (fieldId) {
							void reorderFieldOptions(fieldId, orderedIds);
						}
					}
				},
			}),

			autoScrollForElements({
				element: scrollable,
				canScroll: ({ source }) =>
					isCardData(source.data) || isColumnData(source.data),
			})
		);
	}, [setViewTasks, moveTask, reorderFieldOptions, statusesRef]);

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
			{orderedColumns.map((col, idx) => (
				<React.Fragment key={col.id}>
					{draftColumn && idx === draftIndex && (
						<DraftColumn
							fieldId={draftColumn.fieldId}
							position={draftColumn.position}
							onClose={clearDraftColumn}
						/>
					)}
					<Column
						column={col}
						tasks={viewTasks[col.slug] || []}
						requestAddColumn={requestAddColumn}
					/>
				</React.Fragment>
			))}
			{draftColumn && draftIndex === orderedColumns.length && (
				<DraftColumn
					fieldId={draftColumn.fieldId}
					position={draftColumn.position}
					onClose={clearDraftColumn}
				/>
			)}
		</div>
	);
};
