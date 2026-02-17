import React from 'react';
import {
	isFieldOption,
	Task,
} from '@shared/types/task';
import { getColorStyle } from '@shared/utils/colorUtils';
import { truncateText } from '@shared/utils/textUtils';
import { TaskActionMenu } from '@shared/components/TaskActionMenu';

type HeadingLevel = 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

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
	isCompact?: boolean;
	excludeTags?: string[];
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
	descriptionMaxLength = 100,
	isCompact = false,
	excludeTags = [],
}) => {
	const statusField = task.fields?.find((field) => field.slug === 'status');
	const priorityField = task.fields?.find(
		(field) => field.slug === 'priority'
	);
	const dueDateField = task.fields?.find(
		(field) => field.slug === 'due_date'
	);

	const statusValue = statusField?.values
		? statusField.values[0]?.resolved
		: null;
	const priorityValue = priorityField?.values
		? priorityField.values[0]?.resolved
		: null;
	const dueDateValue = dueDateField?.values
		? dueDateField.values[0]?.raw
		: null;
	const dueDate = dueDateValue
		? new Date(dueDateValue.toString()).toLocaleDateString('en-US', {
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
				<HeadingTag>{task.title}</HeadingTag>
				<div className="task-card-info">
					{/* Tags for compact mode */}
					{isCompact && renderTags()}
					{/* Actions */}
					{hasActions && (
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
				</div>
			</div>
			{/* Description for non-compact mode */}
			{!isCompact && showDescription && task.description && (
				<div className="task-card-description">
					<p>
						{truncateText(task.description, descriptionMaxLength)}
					</p>
				</div>
			)}
			{/* Footer for compact mode, shows description and due date */}
			{isCompact && showDescription && task.description && (
				<div className="task-card-footer">
					<div className="task-card-description">
						<p>
							{truncateText(
								task.description,
								descriptionMaxLength
							)}
						</p>
					</div>
					{dueDate && (
						<span className="task-card-due-date">{dueDate}</span>
					)}
				</div>
			)}
			{/* Footer for non-compact mode, shows tags and due date */}
			{!isCompact && (
				<div className="task-card-footer">
					{renderTags()}
					{dueDate && (
						<span className="task-card-due-date">{dueDate}</span>
					)}
				</div>
			)}
		</div>
	);
};
