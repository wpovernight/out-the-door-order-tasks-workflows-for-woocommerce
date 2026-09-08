import React, {
	useCallback,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from 'react';
import { createPortal } from 'react-dom';
import { __ } from '@wordpress/i18n';
import { Task } from '@sdk/types/task';
import { useTasks } from '@sdk/context/TaskContext';
import { useConfirm } from '@sdk/context/DialogContext';
import { useStatusRoles } from '@sdk/context/StatusRoleContext';
import { createInterpolateElement } from '@wordpress/element';

interface TaskActionMenuProps {
	task: Task;
	// Controlled mode: parent manages open/close state.
	isOpen?: boolean;
	onToggle?: (e: React.MouseEvent) => void;
	// Optional callback overrides.
	onEdit?: (taskId: number) => void;
	onDelete?: (taskId: number) => void;
	onFinish?: (taskId: number) => Promise<boolean>;
	onUnfinish?: (taskId: number) => Promise<boolean>;
	onArchive?: (taskId: number) => Promise<boolean>;
	showEdit?: boolean;
	showDelete?: boolean;
}

export const TaskActionMenu: React.FC<TaskActionMenuProps> = ({
	task,
	isOpen: controlledIsOpen,
	onToggle: controlledOnToggle,
	onEdit,
	onDelete,
	onFinish,
	onUnfinish,
	onArchive,
	showEdit = true,
	showDelete = true,
}) => {
	const {
		finishTask: globalFinishTask,
		unfinishTask: globalUnfinishTask,
		archiveTask: globalArchiveTask,
		deleteTask: globalDeleteTask,
	} = useTasks();
	const confirm = useConfirm();

	// Internal state for uncontrolled mode.
	const [internalIsOpen, setInternalIsOpen] = useState(false);
	const menuRef = useRef<HTMLDivElement>(null);
	const buttonRef = useRef<HTMLButtonElement>(null);
	const menuListRef = useRef<HTMLUListElement>(null);
	// The menu is rendered in a portal on <body>, so it can't be clipped
	// by any ancestor's overflow/containment.
	const [menuStyle, setMenuStyle] = useState<React.CSSProperties>({});

	const isControlled = controlledIsOpen !== undefined;
	const isOpen = isControlled ? controlledIsOpen : internalIsOpen;

	const finishTask = onFinish || globalFinishTask;
	const unfinishTask = onUnfinish || globalUnfinishTask;
	const archiveTask = onArchive || globalArchiveTask;
	const deleteTask = onDelete || globalDeleteTask;

	const { statusRoles } = useStatusRoles();
	const isDone = task.status === statusRoles.done;

	const closeMenu = (e?: React.MouseEvent) => {
		if (!isControlled) {
			setInternalIsOpen(false);
		} else if (controlledOnToggle && isOpen && e) {
			controlledOnToggle(e);
		}
	};

	const handleToggle = (e: React.MouseEvent) => {
		e.preventDefault();
		e.stopPropagation();
		if (controlledOnToggle) {
			controlledOnToggle(e);
		} else {
			setInternalIsOpen((prev) => !prev);
		}
	};

	const handleFinishClick = (e: React.MouseEvent) => {
		e.stopPropagation();
		finishTask(task.id);
		closeMenu(e);
	};

	const handleUnfinishClick = (e: React.MouseEvent) => {
		e.stopPropagation();
		unfinishTask(task.id);
		closeMenu(e);
	};

	const handleArchiveClick = async (e: React.MouseEvent) => {
		e.stopPropagation();
		closeMenu(e);

		const confirmationResult = await confirm({
			title: __('Archive this task?', 'advanced-order-manager-for-woocommerce'),
			message: createInterpolateElement(
				__(
					'Once archived, you can locate this task in the <strong>Task → Archive</strong> tab.',
					'advanced-order-manager-for-woocommerce'
				),
				{ strong: <strong /> }
			),
			confirmText: __('Archive', 'advanced-order-manager-for-woocommerce'),
			cancelText: __('Cancel', 'advanced-order-manager-for-woocommerce'),
			action: 'archive',
		});

		if (!confirmationResult) {
			return;
		}

		archiveTask(task.id);
	};

	const handleEditClick = (e: React.MouseEvent) => {
		e.stopPropagation();
		onEdit?.(task.id);
		closeMenu(e);
	};

	const handleDeleteClick = (e: React.MouseEvent) => {
		e.stopPropagation();
		deleteTask(task.id);
		closeMenu(e);
	};

	// Position the portaled menu relative to the trigger button.
	const updatePosition = useCallback(() => {
		const button = buttonRef.current;
		const menu = menuListRef.current;
		if (!button || !menu) {
			return;
		}

		const buttonRect = button.getBoundingClientRect();
		const menuRect = menu.getBoundingClientRect();
		const GAP = 4;
		const MARGIN = 8; // keep the menu off the viewport edges

		const spaceBelow = window.innerHeight - buttonRect.bottom;
		const spaceAbove = buttonRect.top;
		const openUpward =
			spaceBelow < menuRect.height + GAP && spaceAbove > spaceBelow;

		let top = openUpward
			? buttonRect.top - menuRect.height - GAP
			: buttonRect.bottom + GAP;
		top = Math.min(
			Math.max(top, MARGIN),
			window.innerHeight - menuRect.height - MARGIN
		);

		// Right-align the menu to the trigger, then clamp horizontally.
		let left = buttonRect.right - menuRect.width;
		left = Math.min(
			Math.max(left, MARGIN),
			window.innerWidth - menuRect.width - MARGIN
		);

		setMenuStyle({ position: 'fixed', top, left });
	}, []);

	useLayoutEffect(() => {
		if (isOpen) {
			updatePosition();
		}
	}, [isOpen, updatePosition]);

	// While open, keep the menu anchored to the button on scroll/resize, but
	// close it once the button scrolls out of its scroll container.
	useEffect(() => {
		if (!isOpen) {
			return;
		}

		const isTriggerVisible = () => {
			const button = buttonRef.current;
			if (!button) {
				return false;
			}
			const rect = button.getBoundingClientRect();

			// Clip against the nearest scrolling/clipping ancestor, else the
			// viewport. The button is "visible" only if it intersects that box.
			const bounds = {
				top: 0,
				left: 0,
				right: window.innerWidth,
				bottom: window.innerHeight,
			};
			let ancestor = button.parentElement;
			while (ancestor && ancestor !== document.body) {
				const overflowY = window.getComputedStyle(ancestor).overflowY;
				if (/(auto|scroll|overlay|hidden|clip)/.test(overflowY)) {
					const r = ancestor.getBoundingClientRect();
					bounds.top = Math.max(bounds.top, r.top);
					bounds.left = Math.max(bounds.left, r.left);
					bounds.right = Math.min(bounds.right, r.right);
					bounds.bottom = Math.min(bounds.bottom, r.bottom);
					break;
				}
				ancestor = ancestor.parentElement;
			}

			return (
				rect.bottom > bounds.top &&
				rect.top < bounds.bottom &&
				rect.right > bounds.left &&
				rect.left < bounds.right
			);
		};

		const handleReposition = () => {
			if (!isTriggerVisible()) {
				// Parent owns open state in controlled mode; only self-close
				// when uncontrolled.
				if (!isControlled) {
					setInternalIsOpen(false);
				}
				return;
			}
			updatePosition();
		};

		// Capture phase so scrolls inside nested containers are caught too.
		window.addEventListener('scroll', handleReposition, true);
		window.addEventListener('resize', handleReposition);
		return () => {
			window.removeEventListener('scroll', handleReposition, true);
			window.removeEventListener('resize', handleReposition);
		};
	}, [isOpen, isControlled, updatePosition]);

	// Close menu on outside click (uncontrolled mode only).
	useEffect(() => {
		if (isControlled || !isOpen) {
			return;
		}

		const handleClickOutside = (e: MouseEvent) => {
			const target = e.target as Node;
			// The menu lives in a portal, so check it separately from the
			// trigger, otherwise a menu-item click would close before it fires.
			if (
				!menuRef.current?.contains(target) &&
				!menuListRef.current?.contains(target)
			) {
				setInternalIsOpen(false);
			}
		};

		document.addEventListener('mousedown', handleClickOutside);
		return () =>
			document.removeEventListener('mousedown', handleClickOutside);
	}, [isControlled, isOpen]);

	return (
		<div className="task-card-options" ref={menuRef}>
			<button
				ref={buttonRef}
				className="wpo-button wpo-button-icon options-button"
				type="button"
				onClick={handleToggle}
			>
				<span className="screen-reader-text">
					{__('Options', 'advanced-order-manager-for-woocommerce')}
				</span>
			</button>
			{isOpen &&
				createPortal(
					<ul
						ref={menuListRef}
						className="wpo-action-menu wpo-action-menu-floating"
						style={menuStyle}
					>
						{showEdit && onEdit && (
							<li>
								<button
									type="button"
									className="wpo-button task-edit-menu-item"
									onClick={handleEditClick}
								>
									{__('Edit', 'advanced-order-manager-for-woocommerce')}
								</button>
							</li>
						)}
						<li>
							<button
								type="button"
								className={`wpo-button task-finish-menu-item ${isDone ? 'finished' : ''}`}
								onClick={
									isDone
										? handleUnfinishClick
										: handleFinishClick
								}
							>
								{isDone
									? __(
											'Mark as In Progress',
											'advanced-order-manager-for-woocommerce'
										)
									: __(
											'Mark as Done',
											'advanced-order-manager-for-woocommerce'
										)}
							</button>
						</li>
						<li>
							<button
								type="button"
								className="wpo-button task-archive-menu-item"
								onClick={handleArchiveClick}
							>
								{__('Archive', 'advanced-order-manager-for-woocommerce')}
							</button>
						</li>
						{showDelete && (
							<li>
								<button
									type="button"
									className="wpo-button task-delete-menu-item"
									onClick={handleDeleteClick}
								>
									{__('Delete', 'advanced-order-manager-for-woocommerce')}
								</button>
							</li>
						)}
					</ul>,
					document.body
				)}
		</div>
	);
};
