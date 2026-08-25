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

import { Task, useTaskEdit, TaskCard, useTasks, useConfirm } from '@sdk';
import { getCardData, getCardDropTargetData, isCardData } from '../data';
import { useViewTasks } from '../context/ViewTaskContext';
import { __ } from '@wordpress/i18n';

type CardState =
	| { type: 'idle' } // Indicates no drag interaction occurring
	| { type: 'dragging' } // Indicates the card itself is being dragged
	| { type: 'over'; draggingRect: DOMRect; closestEdge: string } // Indicates a card is being dragged over this card
	| { type: 'dropped' }; // Indicates a card has just been dropped on this card

const IDLE: CardState = { type: 'idle' };

interface CardProps {
	task: Task;
}

export const Card: React.FC<CardProps> = ({ task }) => {
	const outerRef = useRef<HTMLDivElement | null>(null);
	const innerRef = useRef<HTMLDivElement | null>(null);
	const [state, setState] = useState<CardState>(IDLE);

	const { deleteTask, fieldOptions } = useTasks();
	const { selectedTask, selectTask, setViewTasks, finishTask, unfinishTask } =
		useViewTasks();
	const { openEditTaskModal } = useTaskEdit();
	const confirm = useConfirm();

	const taskRef = useRef(task);
	useEffect(() => {
		taskRef.current = task;
	}, [task]);

	// task.status holds a status option's ID, but DnD/grouping work with slugs.
	const columnSlugRef = useRef<string>('');
	useEffect(() => {
		columnSlugRef.current =
			fieldOptions.status?.find((opt) => opt.id === task.status)?.slug ??
			'';
	}, [task.status, fieldOptions.status]);

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
						fromColumn: columnSlugRef.current,
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
						column: columnSlugRef.current,
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

	const [isSelected, setIsSelected] = useState(false);
	useEffect(() => {
		setIsSelected(selectedTask?.id === task.id);
	}, [selectedTask, task.id]);

	const handleCardClick = (e?: React.MouseEvent) => {
		// Prevent selecting during drag operations
		if (state.type === 'dragging') {
			return;
		}

		e?.stopPropagation();
		handleEditTask();
	};

	const handleDeleteClick = async () => {
		const confirmationResult = await confirm({
			title: __('Delete this task?', 'advanced-order-manager'),
			message: __(
				'Are you sure you want to delete this task? This action cannot be undone.',
				'advanced-order-manager'
			),
			confirmText: __('Delete', 'advanced-order-manager'),
			cancelText: __('Cancel', 'advanced-order-manager'),
			action: 'delete',
		});

		if (!confirmationResult) {
			return;
		}

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

	const handleEditTask = () => {
		openEditTaskModal({
			task,
			onTaskSaved: (updatedTask) => {
				// Update local view state to reflect the changes
				setViewTasks((prev) => {
					const updated = structuredClone(prev);
					const oldStatus = task.status;
					const newStatus = updatedTask.status;

					// viewTasks is keyed by status slug; resolve the slugs from option IDs.
					const oldColumnSlug = fieldOptions.status?.find(
						(opt) => opt.id === oldStatus
					)?.slug;
					const newColumnSlug = fieldOptions.status?.find(
						(opt) => opt.id === newStatus
					)?.slug;

					// If status didn't change, update in place to preserve position
					if (
						oldStatus === newStatus &&
						oldColumnSlug &&
						updated[oldColumnSlug]
					) {
						updated[oldColumnSlug] = updated[oldColumnSlug].map(
							(t) =>
								t.id === updatedTask.id
									? { ...updatedTask, status: newStatus }
									: t
						);
					} else {
						// Status changed - remove from old column and add to new column
						for (const status in updated) {
							updated[status] = updated[status].filter(
								(t) => t.id !== updatedTask.id
							);
						}

						// Add task to the end of the new column
						if (newColumnSlug && updated[newColumnSlug]) {
							updated[newColumnSlug].push({
								...updatedTask,
								status: newStatus,
							});
						}
					}

					return updated;
				});
			},
			title: `${__('Edit Task', 'advanced-order-manager')}: ${task.title}`,
		});
	};

	// kanban prefix is used to avoid css conflicts.
	return (
		<div
			ref={outerRef}
			className={`task-card-wrapper ${
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
				isSelected={isSelected}
				onCardClick={handleCardClick}
				onEditClick={handleEditTask}
				onDeleteClick={handleDeleteClick}
				onFinishClick={finishTask}
				onUnfinishClick={unfinishTask}
				className={state.type !== 'idle' ? state.type : ''}
				innerRef={innerRef}
				headingLevel="h5"
				showDescription={true}
				excludeTags={['status']}
			/>
		</div>
	);
};
