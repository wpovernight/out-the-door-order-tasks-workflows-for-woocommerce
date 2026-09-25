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

import {
	FieldOption,
	Task,
	useTasks,
	useConfirm,
	ToastType,
	useToast,
	useOnClickOutside,
	useStatusRoles,
	useTaskCreation,
} from '@sdk';
import {
	getColumnData,
	getColumnDropTargetData,
	isCardData,
	isColumnData,
} from '../data';
import { Card } from './Card';
import { __ } from '@wordpress/i18n';
import { DeleteColumnDialog } from './DeleteColumnDialog';

interface ColumnProps {
	column: FieldOption;
	tasks: Task[];
	requestAddColumn: (fieldId: number, position: number) => void;
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
	requestAddColumn,
}) => {
	const scrollableRef = useRef<HTMLDivElement | null>(null);
	const headerRef = useRef<HTMLDivElement | null>(null);
	const containerRef = useRef<HTMLDivElement | null>(null);
	const columnWrapperRef = useRef<HTMLDivElement | null>(null);
	const titleInputRef = useRef<HTMLInputElement | null>(null);
	const { updateFieldOption, deleteFieldOption } = useTasks();
	const { addToast } = useToast();
	const confirm = useConfirm();
	const { statusRoles } = useStatusRoles();
	const [showDeleteDialog, setShowDeleteDialog] = useState(false);
	const { openCreateTaskModal } = useTaskCreation();

	const isRoleAssigned =
		statusRoles.done === column.id || statusRoles.undone === column.id;
	const [columnTitleEditState, setColumnTitleEditState] = useState<
		'idle' | 'editing'
	>('idle');
	// Mirrored as a ref so `canDrag` reads the current value without forcing
	// the DnD effect to re-register on every edit toggle.
	const editStateRef = useRef(columnTitleEditState);
	useEffect(() => {
		editStateRef.current = columnTitleEditState;
	}, [columnTitleEditState]);
	const [isActionMenuOpen, setIsActionMenuOpen] = useState(false);
	const actionsContainerRef = useRef<HTMLUListElement | null>(null);
	const [draggingState, setDraggingState] = useState<ColumnState>(IDLE);

	useOnClickOutside(actionsContainerRef, () => setIsActionMenuOpen(false));

	const updateState = useCallback(
		(newState: ColumnState) => {
			setDraggingState((prev) => {
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
				canDrag: () => editStateRef.current !== 'editing',
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

	const handleAddTask = () => {
		openCreateTaskModal({
			title: __(
				'Add Task',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			initialValues: {
				statusIndex: column.position - 1,
			},
		});
	};

	const handleTitleSave = () => {
		onColumnUpdate();
		setColumnTitleEditState('idle');
	};

	const handleTitleCancel = () => {
		setColumnTitleEditState('idle');
	};

	const onColumnUpdate = () => {
		const newTitle = titleInputRef.current?.value.trim();

		if (!newTitle || newTitle === column.label) {
			return;
		}

		updateFieldOption(column.field_id, column.id, {
			label: newTitle,
		}).catch((error) => {
			// The optimistic update already reverted the title, inform the user why.
			addToast({
				title: __(
					'Failed to rename column.',
					'out-the-door-order-tasks-workflows-for-woocommerce'
				),
				message:
					error instanceof Error && error.message
						? error.message
						: __(
								'Please try again.',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							),
				type: ToastType.ERROR,
			});
		});
	};

	const handleAddColumn = (placement: 'left' | 'right') => {
		const position =
			placement === 'right' ? column.position + 1 : column.position;

		requestAddColumn(column.field_id, position);

		setIsActionMenuOpen(false);
	};

	const handleDeleteColumn = async () => {
		setIsActionMenuOpen(false);

		// Empty column + role-assigned skips straight to the role-reassign step inside.
		if (isRoleAssigned || tasks.length > 0) {
			setShowDeleteDialog(true);
			return;
		}

		// Empty + not role-assigned → simple confirmation, nothing to lose.
		const confirmed = await confirm({
			title: __(
				'Delete this column?',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			message: __(
				'This action cannot be undone.',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			confirmText: __(
				'Delete',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			cancelText: __(
				'Cancel',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			action: 'delete',
		});

		if (!confirmed) {
			return;
		}

		try {
			await deleteFieldOption(column.field_id, column.id);
		} catch (error) {
			console.error('Failed to delete column:', error);
		}
	};

	return (
		<div
			ref={columnWrapperRef}
			className={`kanban-column ${draggingState.type === 'dragging' ? 'is-dragging' : ''} ${draggingState.type === 'drag-over-empty' ? 'is-column-drag-over' : ''} ${draggingState.type === 'column-drag-over' && draggingState.edge === 'left' ? 'column-drop-indicator-left' : ''} ${draggingState.type === 'column-drag-over' && draggingState.edge === 'right' ? 'column-drop-indicator-right' : ''}`}
		>
			<div className="kanban-column-inner">
				<div
					ref={headerRef}
					className="kanban-column-header"
					tabIndex={0}
				>
					<div className="kanban-column-header-title">
						{columnTitleEditState === 'idle' && (
							<>
								<h4>{column.label}</h4>
								<button
									onClick={() =>
										setColumnTitleEditState('editing')
									}
									className="wpo-button wpo-button-icon edit-title-button"
								>
									<span className="screen-reader-text">
										{__(
											'Edit column name',
											'out-the-door-order-tasks-workflows-for-woocommerce'
										)}
									</span>
								</button>
							</>
						)}

						{columnTitleEditState === 'editing' && (
							<>
								<input
									ref={titleInputRef}
									type="text"
									defaultValue={column.label}
									className="edit-title-input"
									// eslint-disable-next-line jsx-a11y/no-autofocus
									autoFocus
									onFocus={(e) => e.currentTarget.select()}
									onKeyDown={(e) => {
										if (e.key === 'Enter') {
											e.preventDefault();
											handleTitleSave();
										} else if (e.key === 'Escape') {
											e.preventDefault();
											handleTitleCancel();
										}
									}}
								/>
								<div className="edit-title-actions">
									<button
										onClick={handleTitleCancel}
										className="wpo-button wpo-button-icon cancel-edit-title-button"
									>
										<span className="screen-reader-text">
											{__(
												'Cancel',
												'out-the-door-order-tasks-workflows-for-woocommerce'
											)}
										</span>
									</button>
									<span className="wpo-otd-vertical-divider" />
									<button
										onClick={handleTitleSave}
										className="wpo-button wpo-button-icon save-title-button"
									>
										<span className="screen-reader-text">
											{__(
												'Save',
												'out-the-door-order-tasks-workflows-for-woocommerce'
											)}
										</span>
									</button>
								</div>
							</>
						)}
					</div>
					<ul
						ref={actionsContainerRef}
						className="kanban-column-header-actions wpo-otd-row-actions"
					>
						<li>
							<button
								onClick={handleAddTask}
								className="wpo-button wpo-button-icon wpo-otd-add-button"
							>
								<span className="screen-reader-text">
									{__(
										'Create',
										'out-the-door-order-tasks-workflows-for-woocommerce'
									)}
								</span>
							</button>
						</li>
						<li>
							<button
								className="wpo-button wpo-button-icon options-button"
								type="button"
								onClick={(e) => {
									e.stopPropagation();
									setIsActionMenuOpen((prev) => !prev);
								}}
							>
								<span className="screen-reader-text">
									{__(
										'Options',
										'out-the-door-order-tasks-workflows-for-woocommerce'
									)}
								</span>
							</button>
							<ul
								className={`wpo-action-menu column-action-menu ${isActionMenuOpen ? 'is-open' : ''}`}
							>
								<li>
									<button
										type="button"
										className="wpo-button edit-column-title"
										onClick={() => {
											setColumnTitleEditState('editing');
											setIsActionMenuOpen(false);
										}}
									>
										{__(
											'Edit column title',
											'out-the-door-order-tasks-workflows-for-woocommerce'
										)}
									</button>
								</li>
								<li>
									<button
										type="button"
										className="wpo-button add-column-right"
										onClick={() => handleAddColumn('right')}
									>
										{__(
											'Add column right',
											'out-the-door-order-tasks-workflows-for-woocommerce'
										)}
									</button>
								</li>
								<li>
									<button
										type="button"
										className="wpo-button add-column-left"
										onClick={() => handleAddColumn('left')}
									>
										{__(
											'Add column left',
											'out-the-door-order-tasks-workflows-for-woocommerce'
										)}
									</button>
								</li>
								<li>
									<button
										type="button"
										className="wpo-button delete-column"
										onClick={handleDeleteColumn}
									>
										{__(
											'Delete',
											'out-the-door-order-tasks-workflows-for-woocommerce'
										)}
									</button>
								</li>
							</ul>
						</li>
					</ul>
				</div>
				<div ref={scrollableRef} className="kanban-column-scrollable">
					<div
						ref={containerRef}
						className={`kanban-column-container ${
							draggingState.type === 'drag-over-empty'
								? 'show-drop-indicator'
								: ''
						}`}
					>
						{tasks.map((task) => (
							<Card key={task.id} task={task} />
						))}
						<button
							type="button"
							className="wpo-button kanban-column-add-task"
							onClick={handleAddTask}
						>
							{__(
								'Add new task',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
						</button>
					</div>
				</div>
			</div>

			{showDeleteDialog && (
				<DeleteColumnDialog
					column={column}
					tasks={tasks}
					onClose={() => setShowDeleteDialog(false)}
				/>
			)}
		</div>
	);
};
