import React from 'react';
import { isFieldOption, Task } from '@shared/types/task';
import { getColorStyle } from '@shared/utils/colorUtils';
import { truncateText } from '@shared/utils/textUtils';
import { TaskActionMenu } from '@shared/components/TaskActionMenu';
import {
	getFieldObjectValue,
	getTaskDateField,
} from '@shared/utils/fieldUtils';
import { __ } from '@wordpress/i18n';
import { useTasks } from '@shared/context/TaskContext';
import { useConfirm } from '@shared/context/DialogContext';
import { createInterpolateElement } from '@wordpress/element';
import { useStatusRoles } from '@shared/context/StatusRoleContext';

type HeadingLevel = 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
type TagsPosition = 'none' | 'top' | 'bottom';
type ActionDisplayMode = 'dropdown' | 'inline';

interface TaskCardProps {
	task: Task;
	isSelected?: boolean;
	onCardClick?: (e?: React.MouseEvent) => void;
	onEditClick?: (taskId: number) => void;
	onDeleteClick?: (taskId: number) => void;
	onFinishClick?: (taskId: number) => Promise<boolean>;
	onUnfinishClick?: (taskId: number) => Promise<boolean>;
	onArchiveClick?: (taskId: number) => Promise<boolean>;
	className?: string;
	innerRef?: React.RefObject<HTMLDivElement | null>;
	headingLevel?: HeadingLevel;
	showDescription?: boolean;
	descriptionMaxLength?: number;
	tagsPosition?: TagsPosition;
	showOrder?: boolean;
	ActionDisplayMode?: ActionDisplayMode;
	IncludedActions?: ('edit' | 'delete' | 'finish' | 'unfinish' | 'archive')[];
	FinishAsCheckbox?: boolean;
	excludeTags?: string[];
	showDueDate?: boolean;
	orderInline?: boolean;
}

export const TaskCard: React.FC<TaskCardProps> = ({
	task,
	isSelected = false,
	onCardClick,
	onEditClick,
	onDeleteClick,
	onFinishClick,
	onUnfinishClick,
	onArchiveClick,
	className = '',
	innerRef,
	headingLevel = 'h3',
	showDescription = false,
	descriptionMaxLength = 70,
	tagsPosition = 'bottom',
	showOrder = true,
	ActionDisplayMode = 'dropdown',
	IncludedActions = ['edit', 'delete', 'finish', 'unfinish', 'archive'],
	FinishAsCheckbox = false,
	excludeTags = [],
	showDueDate = true,
	orderInline = false,
}) => {
	const {
		finishTask: globalFinishTask,
		unfinishTask: globalUnfinishTask,
		archiveTask: globalArchiveTask,
	} = useTasks();
	const confirm = useConfirm();
	const { statusRoles } = useStatusRoles();
	const finishTask = onFinishClick || globalFinishTask;
	const unfinishTask = onUnfinishClick || globalUnfinishTask;
	const archiveTask = onArchiveClick || globalArchiveTask;

	const statusField = task.fields?.find((field) => field.slug === 'status');
	const priorityField = task.fields?.find(
		(field) => field.slug === 'priority'
	);
	const statusValue = statusField?.values
		? statusField.values[0]?.resolved
		: null;
	const priorityValue = priorityField?.values
		? priorityField.values[0]?.resolved
		: null;
	const dueDateObject = getTaskDateField(task, 'due_date');
	const dueDate = dueDateObject
		? dueDateObject.toLocaleDateString(undefined, {
				month: 'short',
				day: 'numeric',
				year: 'numeric',
			})
		: null;

	const handleCardClick = (e: React.MouseEvent) => {
		if (onCardClick) {
			onCardClick(e);
		}
	};

	const hasActions = onEditClick || onDeleteClick;

	// Create dynamic heading element
	const HeadingTag = headingLevel;

	const renderTags = () => {
		return (
			<>
				<ul className="task-card-tags">
					{/* Status Tags */}
					{statusValue &&
						isFieldOption(statusValue) &&
						!excludeTags?.includes(statusField?.slug || '') && (
							<li
								className={`wpo-aom-tag task-card-status status-${statusValue.slug}`}
								style={getColorStyle(statusValue.color)}
							>
								{statusValue.label}
							</li>
						)}
					{/* Priority Tag */}
					{priorityValue &&
						isFieldOption(priorityValue) &&
						!excludeTags?.includes(priorityField?.slug || '') && (
							<li
								className={`wpo-aom-tag task-card-priority priority-${priorityValue.slug}`}
								style={getColorStyle(priorityValue.color)}
							>
								{priorityValue.label}
							</li>
						)}
				</ul>
			</>
		);
	};

	const renderDescription = () => {
		if (!task.description) {
			return null;
		}

		return (
			<div className="task-card-description">
				<p>{truncateText(task.description, descriptionMaxLength)}</p>
			</div>
		);
	};

	const renderDueDate = () => {
		if (!dueDate || !showDueDate) {
			return null;
		}

		let classes = 'task-card-due-date';

		if (
			dueDateObject &&
			dueDateObject.setHours(0, 0, 0, 0) <
				new Date().setHours(0, 0, 0, 0) &&
			task.status !== statusRoles.done
		) {
			classes += ' overdue';
		}

		return <span className={classes}>{dueDate}</span>;
	};

	const renderAssociatedOrder = (inline = false) => {
		const order = getFieldObjectValue(task, 'order');

		if (!order) {
			return null;
		}

		return (
			<div
				className={`task-card-order${inline ? ' task-card-order-inline' : ''}`}
			>
				<a
					href={order.url as string}
					target="_blank"
					rel="noopener noreferrer"
					onClick={(e) => e.stopPropagation()}
				>
					{order.full_name as string} • #{order.id as number}
				</a>
			</div>
		);
	};

	const handleFinishClick = (e: React.SyntheticEvent) => {
		e.stopPropagation();
		finishTask(task.id);
	};

	const handleUnfinishClick = (e: React.SyntheticEvent) => {
		e.stopPropagation();
		unfinishTask(task.id);
	};

	const handleArchiveClick = async (e: React.SyntheticEvent) => {
		e.stopPropagation();

		const confirmationResult = await confirm({
			title: __('Archive this task?', 'wpo-advanced-order-manager'),
			message: createInterpolateElement(
				__(
					'Once archived, you can locate this task in the <strong>Task → Archive</strong> tab.',
					'wpo-advanced-order-manager'
				),
				{ strong: <strong /> }
			),
			confirmText: __('Archive', 'wpo-advanced-order-manager'),
			cancelText: __('Cancel', 'wpo-advanced-order-manager'),
			action: 'archive',
		});

		if (!confirmationResult) {
			return;
		}

		archiveTask(task.id);
	};

	const isDone = task.status === statusRoles.done;

	const renderActionInline = () => {
		if (IncludedActions.length === 0) {
			return null;
		}

		return (
			<ul className="wpo-aom-task-actions task-card-actions">
				{IncludedActions.includes('edit') && onEditClick && (
					<li>
						<button
							className="wpo-button wpo-button-icon task-edit"
							type="button"
							onClick={(e) => {
								e.stopPropagation();
								onEditClick(task.id);
							}}
							title={__('Edit', 'wpo-advanced-order-manager')}
						>
							<span className="screen-reader-text">
								{__('Edit', 'wpo-advanced-order-manager')}
							</span>
						</button>
					</li>
				)}
				{IncludedActions.includes('archive') && (
					<li>
						<button
							className="wpo-button wpo-button-icon task-archive"
							type="button"
							onClick={handleArchiveClick}
							title={__('Archive', 'wpo-advanced-order-manager')}
						>
							<span className="screen-reader-text">
								{__('Archive', 'wpo-advanced-order-manager')}
							</span>
						</button>
					</li>
				)}
				{(IncludedActions.includes('finish') ||
					IncludedActions.includes('unfinish')) &&
					!FinishAsCheckbox && (
						<li>
							<button
								className={`wpo-button wpo-button-icon task-finish ${isDone ? 'finished' : ''}`}
								type="button"
								onClick={
									isDone
										? handleUnfinishClick
										: handleFinishClick
								}
								title={
									isDone
										? __(
												'Mark as In Progress',
												'wpo-advanced-order-manager'
											)
										: __(
												'Mark as Done',
												'wpo-advanced-order-manager'
											)
								}
							>
								<span className="screen-reader-text">
									{isDone
										? __(
												'Mark as In Progress',
												'wpo-advanced-order-manager'
											)
										: __(
												'Mark as Done',
												'wpo-advanced-order-manager'
											)}
								</span>
							</button>
						</li>
					)}
				{IncludedActions.includes('delete') && onDeleteClick && (
					<li>
						<button
							className="wpo-button wpo-button-icon task-delete"
							type="button"
							onClick={(e) => {
								e.stopPropagation();
								onDeleteClick(task.id);
							}}
							title={__('Delete', 'wpo-advanced-order-manager')}
						>
							<span className="screen-reader-text">
								{__('Delete', 'wpo-advanced-order-manager')}
							</span>
						</button>
					</li>
				)}
			</ul>
		);
	};

	return (
		// eslint-disable-next-line jsx-a11y/no-static-element-interactions
		<div
			ref={innerRef}
			className={`task-card ${className} ${isSelected ? 'selected' : ''}`}
			onClick={handleCardClick}
			onKeyDown={(e) => {
				if (e.key === 'Enter' || e.key === ' ') {
					handleCardClick(e as unknown as React.MouseEvent);
				}
			}}
		>
			<div className="task-card-header">
				{FinishAsCheckbox && (
					<label
						htmlFor={`task-finish-${task.id}`}
						className={`task-finish-checkbox ${isDone ? 'finished' : ''}`}
					>
						<span className="screen-reader-text">
							{isDone
								? __(
										'Mark as In Progress',
										'wpo-advanced-order-manager'
									)
								: __(
										'Mark as Done',
										'wpo-advanced-order-manager'
									)}
						</span>
						<input
							id={`task-finish-${task.id}`}
							type="checkbox"
							checked={isDone}
							onChange={(e) => {
								e.stopPropagation();
								if (e.target.checked) {
									handleFinishClick(e);
								} else {
									handleUnfinishClick(e);
								}
							}}
						/>
					</label>
				)}
				<HeadingTag>{task.title}</HeadingTag>
				{orderInline && showOrder && renderAssociatedOrder(true)}
				<div className="task-card-header-info">
					{/* Tags for compact mode */}
					{tagsPosition === 'top' && renderTags()}
					{/* Actions */}
					{hasActions && ActionDisplayMode === 'dropdown' && (
						<TaskActionMenu
							task={task}
							onEdit={onEditClick}
							onDelete={onDeleteClick}
							onFinish={onFinishClick}
							onUnfinish={onUnfinishClick}
							onArchive={onArchiveClick}
							showEdit={!!onEditClick}
							showDelete={!!onDeleteClick}
						/>
					)}

					{hasActions &&
						ActionDisplayMode === 'inline' &&
						renderActionInline()}
				</div>
			</div>
			{showDescription && !showOrder && tagsPosition === 'none' ? (
				<div className="task-card-description-date">
					{renderDescription()}
					{renderDueDate()}
				</div>
			) : (
				showDescription && renderDescription()
			)}

			{(showOrder && !orderInline) ||
			tagsPosition !== 'none' ||
			(!showDescription && showDueDate && dueDate) ? (
				<div className="task-card-footer">
					<div className="task-card-info">
						{tagsPosition === 'bottom' && renderTags()}
						{showOrder && !orderInline && renderAssociatedOrder()}
					</div>
					{renderDueDate()}
				</div>
			) : (
				''
			)}
		</div>
	);
};
