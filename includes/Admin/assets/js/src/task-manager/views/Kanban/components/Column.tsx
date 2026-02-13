import React, { useCallback, useEffect, useRef, useState } from 'react';
import invariant from 'tiny-invariant';
import { combine } from '@atlaskit/pragmatic-drag-and-drop/combine';
import {
	draggable,
	dropTargetForElements,
} from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import { autoScrollForElements } from '@atlaskit/pragmatic-drag-and-drop-auto-scroll/element';
import {
	attachClosestEdge,
	extractClosestEdge,
	type Edge,
} from '@atlaskit/pragmatic-drag-and-drop-hitbox/closest-edge';

import { FieldOption, Task } from '@shared/types/task';
import {
	getColumnData,
	getColumnDropTargetData,
	isCardData,
	isColumnData,
} from '../data';
import { Card } from './Card';
import { __ } from '@wordpress/i18n';
import { useTaskCreation } from '@shared/hooks/useTaskFormModal';
import { useViewTasks } from '@taskManager/views/Kanban/context/ViewTaskContext';

interface ColumnProps {
	column: FieldOption;
	tasks: Task[];
	openOptionsCardId: number | null;
	setOpenOptionsCardId: React.Dispatch<React.SetStateAction<number | null>>;
}

type ColumnState =
	| { type: 'idle' } // No drag interaction occurring
	| { type: 'drag-over-card'; draggingRect: DOMRect } // Indicates a card is being dragged over this column
	| { type: 'drag-over-empty' } // Indicates a card is being dragged over empty space in this column
	| { type: 'dragging' } // Indicates the column itself is being dragged
	| { type: 'column-drag-over'; edge: Edge | null }; // Indicates another column is being dragged over this column

const IDLE: ColumnState = { type: 'idle' };

export const Column: React.FC<ColumnProps> = ({
	column,
	tasks,
	openOptionsCardId,
	setOpenOptionsCardId,
}) => {
	const scrollableRef = useRef<HTMLDivElement | null>(null);
	const headerRef = useRef<HTMLDivElement | null>(null);
	const containerRef = useRef<HTMLDivElement | null>(null);
	const columnWrapperRef = useRef<HTMLDivElement | null>(null);
	const { openCreateTaskModal } = useTaskCreation();

	const [state, setState] = useState<ColumnState>(IDLE);

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
		const columnWrapper = columnWrapperRef.current;
		invariant(scrollable && header && container && columnWrapper);

		const columnData = getColumnData({ column: column.slug });
		const columnDropTargetData = getColumnDropTargetData({
			column: column.slug,
		});

		return combine(
			// Make the column header draggable
			draggable({
				element: header,
				getInitialData: () => columnData,
				onDragStart: () => updateState({ type: 'dragging' }),
				onDrop: resetState,
			}),

			// Make the column wrapper a drop target for other columns
			dropTargetForElements({
				element: columnWrapper,
				canDrop: ({ source }) => isColumnData(source.data),
				getData: ({ input }) =>
					attachClosestEdge(columnDropTargetData, {
						element: columnWrapper,
						input,
						allowedEdges: ['left', 'right'],
					}),
				onDragEnter: ({ source, self }) => {
					if (
						isColumnData(source.data) &&
						source.data.column !== column.slug
					) {
						const edge = extractClosestEdge(self.data);
						updateState({ type: 'column-drag-over', edge });
					}
				},
				onGenerateDragPreview() {
					// Close options menu before drag preview is generated
					onOptionToggle(null);
				},
				onDrag: ({ source, self }) => {
					if (
						isColumnData(source.data) &&
						source.data.column !== column.slug
					) {
						const edge = extractClosestEdge(self.data);
						updateState({ type: 'column-drag-over', edge });
					}
				},
				onDragLeave: resetState,
				onDrop: resetState,
			}),

			// Make column a valid drop target for cards.
			dropTargetForElements({
				element: container,
				canDrop: ({ source }) => isCardData(source.data),
				getData: () => columnData,
				onDragEnter({ source, location }) {
					if (!isCardData(source.data)) {
						return;
					}

					// Check if dragging over empty space or over a card
					const hasNoTargets =
						location.current.dropTargets.length === 1;
					if (hasNoTargets) {
						updateState({ type: 'drag-over-empty' });
					} else {
						updateState({
							type: 'drag-over-card',
							draggingRect: source.data.rect,
						});
					}
				},
				onDrag({ source, location }) {
					if (!isCardData(source.data)) {
						return;
					}

					// Check if dragging over empty space or over a card
					const hasNoTargets =
						location.current.dropTargets.length === 1;
					if (hasNoTargets) {
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

					// If no inner card target is under cursor, treat it as empty space
					const hasNoTargets =
						location.current.dropTargets.length === 1;
					// `1` means only the column itself is targeted (no inner card)
					if (hasNoTargets) {
						updateState({ type: 'drag-over-empty' });
						return;
					}

					updateState({
						type: 'drag-over-card',
						draggingRect: source.data.rect,
					});
				},

				onDragLeave: resetState,

				onDrop() {
					// Card drop logic is handled by Board.tsx monitor
					// Reset visual state after drop
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
	}, [column.slug, updateState, resetState]);

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

	const { setViewTasks } = useViewTasks();

	const openTaskCreationModal = () => {
		openCreateTaskModal({
			columnId: column.id,
			onTaskSaved: (newTask) => {
				// Update local view state to include the new task
				setViewTasks((prev) => {
					const updated = structuredClone(prev);
					const status = newTask.status || column.slug;
					if (updated[status]) {
						updated[status].push({
							...newTask,
							status,
						});
					}
					return updated;
				});
			},
			title: __('Add Task', 'wpo-aom'),
		});
	};

	const onOptionToggle = (taskId: number | null) => {
		setOpenOptionsCardId(taskId);
	};

	return (
		<div
			ref={columnWrapperRef}
			className={`kanban-column ${state.type === 'dragging' ? 'is-dragging' : ''} ${state.type === 'drag-over-empty' ? 'is-column-drag-over' : ''} ${state.type === 'column-drag-over' && state.edge === 'left' ? 'column-drop-indicator-left' : ''} ${state.type === 'column-drag-over' && state.edge === 'right' ? 'column-drop-indicator-right' : ''}`}
		>
			<div className="kanban-column-inner">
				<div
					ref={headerRef}
					className="kanban-column-header"
					tabIndex={0}
				>
					<h2>{column.label}</h2>
					<button
						onClick={openTaskCreationModal}
						className="wpo-button wpo-button-icon wpo-aom-add-button"
					>
						<span className="screenReader">
							{__('Create', 'wpo-aom')}
						</span>
					</button>
				</div>
				<div ref={scrollableRef} className="kanban-column-scrollable">
					<div
						ref={containerRef}
						className={`kanban-column-container ${
							state.type === 'drag-over-empty'
								? 'show-drop-indicator'
								: ''
						}`}
					>
						{tasks.map((task) => (
							<Card
								key={task.id}
								task={task}
								isOptionsOpen={openOptionsCardId === task.id}
								onOptionsToggle={onOptionToggle}
							/>
						))}
					</div>
				</div>
			</div>
		</div>
	);
};
