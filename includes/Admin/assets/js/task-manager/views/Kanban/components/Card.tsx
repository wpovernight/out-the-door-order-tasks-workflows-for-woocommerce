import React, { useEffect, useRef, useState } from 'react';
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
	| { type: 'idle' }
	| { type: 'dragging' }
	| { type: 'over'; draggingRect: DOMRect; closestEdge: string };

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

	useEffect(() => {
		console.log('[Card state]', state); // ToDo: Remove debug log
	}, [state]);

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
						task,
						fromColumn: task.status,
						rect: element.getBoundingClientRect(),
					}),
				onDragStart() {
					setState({ type: 'dragging' });
				},
				onDrop() {
					setState(IDLE);
				},
			}),

			// ------------------------------
			// Make droppable (for reordering)
			// ------------------------------
			dropTargetForElements({
				element: outer,
				getIsSticky: () => true,
				// canDrop({ source }) {
				//     return isCardData(source.data);
				// },
				getData: ({ element, input }) => {
					const data = getCardDropTargetData({
						task,
						column: task.status,
					});
					return attachClosestEdge(data, {
						element,
						input,
						allowedEdges: ['top', 'bottom'],
					});
				},
				onDragEnter({ source, self }) {
					if (!isCardData(source.data)) {
						return;
					}
					if (source.data.task.id === task.id) {
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
					if (!isCardData(source.data)) {
						return;
					}
					if (source.data.task.id === task.id) {
						return;
					}
					setState(IDLE);
				},
				onDrop() {
					setState(IDLE);
				},
			})
		);
	}, [task]);

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
