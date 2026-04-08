import React, { useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Task, TASK_FINISH_STATUS_SLUG } from '@shared/types/task';
import { useTasks } from '@shared/context/TaskContext';

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

	// Internal state for uncontrolled mode.
	const [internalIsOpen, setInternalIsOpen] = useState(false);
	const menuRef = useRef<HTMLDivElement>(null);

	const isControlled = controlledIsOpen !== undefined;
	const isOpen = isControlled ? controlledIsOpen : internalIsOpen;

	const finishTask = onFinish || globalFinishTask;
	const unfinishTask = onUnfinish || globalUnfinishTask;
	const archiveTask = onArchive || globalArchiveTask;
	const deleteTask = onDelete || globalDeleteTask;

	const isDone = task.status === TASK_FINISH_STATUS_SLUG;

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

	const handleArchiveClick = (e: React.MouseEvent) => {
		e.stopPropagation();
		archiveTask(task.id);
		closeMenu(e);
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

	// Close menu on outside click (uncontrolled mode only).
	useEffect(() => {
		if (isControlled || !isOpen) {
			return;
		}

		const handleClickOutside = (e: MouseEvent) => {
			if (
				menuRef.current &&
				!menuRef.current.contains(e.target as Node)
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
				className="wpo-button wpo-button-icon wpo-options-button"
				type="button"
				onClick={handleToggle}
			>
				<span className="screenReader">{__('Options', 'wpo-aom')}</span>
			</button>
			{isOpen && (
				<ul className="wpo-action-menu">
					{showEdit && onEdit && (
						<li>
							<button
								type="button"
								className="wpo-button task-edit-menu-item"
								onClick={handleEditClick}
							>
								{__('Edit', 'wpo-aom')}
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
								? __('Mark as In Progress', 'wpo-aom')
								: __('Mark as Done', 'wpo-aom')}
						</button>
					</li>
					<li>
						<button
							type="button"
							className="wpo-button task-archive-menu-item"
							onClick={handleArchiveClick}
						>
							{__('Archive', 'wpo-aom')}
						</button>
					</li>
					{showDelete && (
						<li>
							<button
								type="button"
								className="wpo-button task-delete-menu-item"
								onClick={handleDeleteClick}
							>
								{__('Delete', 'wpo-aom')}
							</button>
						</li>
					)}
				</ul>
			)}
		</div>
	);
};
