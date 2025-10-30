import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
	draggable,
	dropTargetForElements,
} from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import {
	attachClosestEdge,
	extractClosestEdge,
} from '@atlaskit/pragmatic-drag-and-drop-hitbox/closest-edge';
import { combine } from '@atlaskit/pragmatic-drag-and-drop/combine';
import invariant from 'tiny-invariant';

import { Task } from '../../../types/task';
import { getCardData, getCardDropTargetData, isCardData } from '../data';

// ------------------------------
// Visual state
// ------------------------------
type CardState =
	| { type: 'idle' } // Indicates no drag interaction occurring
	| { type: 'dragging' } // Indicates the card itself is being dragged
	| { type: 'over'; draggingRect: DOMRect; closestEdge: string }; // Indicates a card is being dragged over this card

const IDLE: CardState = { type: 'idle' };

// ------------------------------
// Component
// ------------------------------

interface CardProps {
	task: Task;
}

export const Card: React.FC<CardProps> = ({ task }) => {
	const outerRef = useRef<HTMLDivElement | null>(null);
	const innerRef = useRef<HTMLDivElement | null>(null);
	const [state, setState] = useState<CardState>(IDLE);

	const taskRef = useRef(task);
	useEffect(() => {
		taskRef.current = task;
	}, [task]);

	const updateState = useCallback(
		(newState: CardState) => {
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
		const outer = outerRef.current;
		const inner = innerRef.current;
		invariant(outer && inner);

		return combine(
			// ------------------------------
			// Make draggable
			// ------------------------------
			draggable({
				element: inner,
				getInitialData: ({ element }) =>
					getCardData({
						task: taskRef.current,
						fromColumn: taskRef.current.status,
						rect: element.getBoundingClientRect(),
					}),
				onDragStart() {
					setState({ type: 'dragging' });
				},
				onDrop: resetState,
			}),

			// ------------------------------
			// Make droppable (for reordering)
			// ------------------------------
			dropTargetForElements({
				element: outer,
				getIsSticky: ({ source }) => isCardData(source.data),
				canDrop({ source }) {
					return isCardData(source.data);
				},
				getData: ({ element, input }) => {
					const data = getCardDropTargetData({
						task: taskRef.current,
						column: taskRef.current.status,
					});
					return attachClosestEdge(data, {
						element,
						input,
						allowedEdges: ['top', 'bottom'],
					});
				},
				onDragEnter({ source, self }) {
					if (
						!isCardData(source.data) ||
						source.data.task.id === taskRef.current.id
					) {
						return;
					}

					const edge = extractClosestEdge(self.data);
					if (!edge) {
						return;
					}

					setState({
						type: 'over',
						draggingRect: source.data.rect,
						closestEdge: edge,
					});
				},
				onDragLeave({ source }) {
					if (
						!isCardData(source.data) ||
						source.data.task.id === taskRef.current.id
					) {
						return;
					}

					resetState();
				},
				onDrop({ source }) {
					if (
						!isCardData(source.data) ||
						source.data.task.id === taskRef.current.id
					) {
						return;
					}

					resetState();
				},
			})
		);
	}, [resetState, taskRef]);

	return (
		<div ref={outerRef} className="kanban-card-wrapper">
			{/* kanban prefix is used to avoid css conflicts */}
			{/* Drop indicator above */}
			{state.type === 'over' && state.closestEdge === 'top' && (
				<span className="kanban-drop-indicator top" />
			)}
			<div
				ref={innerRef}
				className={`kanban-card ${state.type !== 'idle' ? state.type : ''}`}
			>
				<h3>{task.title}</h3>
				<p>{task.description}</p>
			</div>
			{/* Drop indicator below */}
			{state.type === 'over' && state.closestEdge === 'bottom' && (
				<span className="kanban-drop-indicator bottom" />
			)}
		</div>
	);
};
