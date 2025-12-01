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

import { Task } from '@shared/types/task';
import { getCardData, getCardDropTargetData, isCardData } from '../data';
import { useViewTasks } from '../context/ViewTaskContext';
import { useSidebarModal } from '@shared/context/SidebarModalContext';
import { TaskForm } from '@shared/components/TaskForm';
import { TaskCard } from '@shared/components/TaskCard';
import { useTasks } from '@shared/context/TaskContext';
import { useTaskManagerData } from '@taskManager/hooks/useTaskManagerData';

// ------------------------------
// Visual state
// ------------------------------
type CardState =
	| { type: 'idle' } // Indicates no drag interaction occurring
	| { type: 'dragging' } // Indicates the card itself is being dragged
	| { type: 'over'; draggingRect: DOMRect; closestEdge: string } // Indicates a card is being dragged over this card
	| { type: 'dropped' }; // Indicates a card has just been dropped on this card

const IDLE: CardState = { type: 'idle' };

// ------------------------------
// Component
// ------------------------------

interface CardProps {
	task: Task;
	isOptionsOpen: boolean;
	onOptionsToggle: (id: number | null) => void;
}

export const Card: React.FC<CardProps> = ({
	task,
	isOptionsOpen,
	onOptionsToggle,
}) => {
	const outerRef = useRef<HTMLDivElement | null>(null);
	const innerRef = useRef<HTMLDivElement | null>(null);
	const [state, setState] = useState<CardState>(IDLE);

	const { i18n } = useTaskManagerData();
	const { deleteTask } = useTasks();
	const { selectedTask, selectTask, setViewTasks } = useViewTasks();
	const { openSidebar, closeSidebar } = useSidebarModal();

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
					updateState({ type: 'dragging' });
				},
				onDrop() {
					updateState({ type: 'dropped' });
					selectTask(taskRef.current);
				},
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
				onDrag({ source, self }) {
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

					setState((prevState: CardState): CardState => {
						if (
							prevState.type === 'over' &&
							prevState.closestEdge === edge
						) {
							return prevState;
						}

						return {
							type: 'over',
							draggingRect: source.data.rect as DOMRect,
							closestEdge: edge,
						};
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
	}, [updateState, resetState, taskRef, selectTask]);

	// ToDo: Fix
	const [isSelected, setIsSelected] = useState(false);
	useEffect(() => {
		setIsSelected(selectedTask?.id === task.id);
	}, [selectedTask, task.id]);

	const handleCardClick = () => {
		selectTask(task);
	};

	const handleOptionsClick = (e: React.MouseEvent) => {
		e.preventDefault();
		e.stopPropagation();
		onOptionsToggle(isOptionsOpen ? null : task.id);
	};

	const handleRemoveTask = (e: React.MouseEvent) => {
		e.preventDefault();
		deleteTask(task.id);
		setViewTasks((prev) => {
			const updated = structuredClone(prev);
			for (const status in updated) {
				updated[status] = updated[status].filter(
					(t) => t.id !== task.id
				);
			}
			return updated;
		});
	};

	const handleEditTask = (e: React.MouseEvent) => {
		e.preventDefault();
		e.stopPropagation();
		onOptionsToggle(null);

		openSidebar(
			<TaskForm
				task={task}
				onDone={closeSidebar}
				onTaskSaved={(updatedTask) => {
					setViewTasks((prev) => {
						const updated = structuredClone(prev);
						const newStatus = updatedTask.status;

						// Remove task from all columns (in case status changed)
						for (const status in updated) {
							updated[status] = updated[status].filter(
								(t) => t.id !== updatedTask.id
							);
						}

						// Add task to the correct column
						if (updated[newStatus]) {
							updated[newStatus].push({
								...updatedTask,
								status: newStatus,
							});
						}

						return updated;
					});
				}}
			/>,
			{
				title: `${i18n.kanban.editTask}: ${task.title}`,
			}
		);
	};

	// kanban prefix is used to avoid css conflicts.
	return (
		<div
			ref={outerRef}
			className={`kanban-card-wrapper ${
				state.type === 'over' && state.closestEdge === 'top'
					? 'drop-indicator-top'
					: ''
			} ${
				state.type === 'over' && state.closestEdge === 'bottom'
					? 'drop-indicator-bottom'
					: ''
			}`}
		>
			<TaskCard
				task={task}
				isOptionsOpen={isOptionsOpen}
				isSelected={isSelected}
				onCardClick={handleCardClick}
				onOptionsClick={handleOptionsClick}
				onEditClick={handleEditTask}
				onDeleteClick={handleRemoveTask}
				className={state.type !== 'idle' ? state.type : ''}
				innerRef={innerRef}
				actionsDisplayMode="menu"
				headingLevel="h3"
				i18n={{
					options: i18n.kanban.options,
					edit: i18n.actions.edit,
					delete: i18n.actions.delete,
					dueDateLabel: i18n.kanban.dueDateLabel,
				}}
			/>
		</div>
	);
};
