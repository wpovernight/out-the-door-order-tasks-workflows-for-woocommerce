import React, { useCallback, useEffect, useRef, useState } from 'react';
import invariant from 'tiny-invariant';
import { combine } from '@atlaskit/pragmatic-drag-and-drop/combine';
import {
	draggable,
	dropTargetForElements,
} from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import { autoScrollForElements } from '@atlaskit/pragmatic-drag-and-drop-auto-scroll/element';

import { useTasks } from '../../../context/TaskContext';
import { FieldOption, Task } from '../../../types/task';
import { getColumnData, isCardData } from '../data';
import { Card } from './Card';

interface ColumnProps {
	column: FieldOption;
	tasks: Task[];
}

type ColumnState =
	| { type: 'idle' } // No drag interaction occurring
	| { type: 'drag-over-card'; draggingRect: DOMRect } // Indicates a card is being dragged over this column
	| { type: 'drag-over-empty' } // Indicates a card is being dragged over empty space in this column
	| { type: 'dragging' }; // Indicates the column itself is being dragged

const IDLE: ColumnState = { type: 'idle' };

export const Column: React.FC<ColumnProps> = ({ column, tasks }) => {
	const scrollableRef = useRef<HTMLDivElement | null>(null);
	const headerRef = useRef<HTMLDivElement | null>(null);
	const containerRef = useRef<HTMLDivElement | null>(null);

	const [state, setState] = useState<ColumnState>(IDLE);
	// const {saveTask} = useTasks();

	const tasksRef = useRef(tasks);
	useEffect(() => {
		tasksRef.current = tasks;
	}, [tasks]);

	const updateState = useCallback(
		(newState: ColumnState) => {
			setState((prev) => {
				if (prev.type === newState.type) {
					return prev;
				}
				return newState;
			});
		},
		[] // no dependencies; safe because setState is stable
	);

	const resetState = useCallback(() => {
		updateState(IDLE);
	}, [updateState]);

	useEffect(() => {
		// Used to prevent state updates after unmount,
		let isMounted = true;

		const scrollable = scrollableRef.current;
		const header = headerRef.current;
		const container = containerRef.current;
		invariant(scrollable && header && container);

		const columnData = getColumnData({ column: column.label });

		return combine(
			// Make the column draggable (for future enhancement) // ToDo: Complete this feature
			draggable({
				element: header,
				getInitialData: () => columnData,
				onDragStart: resetState,
				onDrop: resetState,
			}),

			// Make column a valid drop target for cards.
			dropTargetForElements({
				element: container,
				canDrop: ({ source }) => isCardData(source.data),
				getData: () => columnData,
				onDragEnter({ source }) {
					if (!isCardData(source.data)) {
						return;
					}

					// Recompute current tasks dynamically
					const currentTasks = tasksRef.current.filter(
						(t) => t.status === column.label
					);

					if (currentTasks.length === 0) {
						updateState({ type: 'drag-over-empty' });
					} else {
						updateState({
							type: 'drag-over-card',
							draggingRect: source.data.rect,
						});
					}
				},
				onDropTargetChange({ source, location }) {
					if (!isCardData(source.data)) {
						return;
					}

					// If no inner card target is under cursor, treat it as empty
					const hasNoTargets =
						location.current.dropTargets.length === 1;
					// `1` means only the column itself is targeted (no inner card)
					if (hasNoTargets) {
						const currentTasks = tasksRef.current.filter(
							(t) => t.status === column.label
						);

						if (currentTasks.length === 0) {
							updateState({ type: 'drag-over-empty' });
							return;
						}
					}

					updateState({
						type: 'drag-over-card',
						draggingRect: source.data.rect,
					});
				},

				onDragLeave: resetState,

				async onDrop({ source }) {
					if (!isCardData(source.data)) {
						return;
					}
					const { task, fromColumn } = source.data;
					if (fromColumn !== column.label) {
						// await saveTask(task.id, { status: column.label });
					}
					if (isMounted) {
						resetState();
					}
				},
			}),

			// Cleanup function to set isMounted to false on unmount.
			() => {
				isMounted = false;
			}
		);
	}, [column.label, updateState, resetState]);

	// Auto-scroll while dragging cards.
	// This is separated from the above useEffect to avoid re-initializing.
	useEffect(() => {
		const scrollable = scrollableRef.current;
		if (!scrollable) {
			return;
		}

		return autoScrollForElements({
			element: scrollable,
			canScroll: ({ source }) => isCardData(source.data),
		});
	}, []);

	// ToDo: Add visual drop indicators for columns
	return (
		<div className="kanban-column">
			<div ref={headerRef} className="kanban-column-header" tabIndex={0}>
				<h2>{column.label}</h2>
			</div>
			<div ref={scrollableRef} className="kanban-column-scrollable">
				<div ref={containerRef} className="kanban-column-container">
					{tasks.map((task) => (
						<Card key={task.id} task={task} />
					))}

					{/* Separator line at the bottom when dragging over empty space */}
					{state.type === 'drag-over-empty' && (
						<span className="kanban-drop-indicator" />
					)}
				</div>
			</div>
		</div>
	);
};
