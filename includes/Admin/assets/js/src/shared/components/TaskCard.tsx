import React from 'react';
import { isFieldOption, Task } from '@shared/types/task';
import { getColorStyle } from '@shared/utils/colorUtils';
import { truncateText } from '@shared/utils/textUtils';
import { TaskActionMenu } from '@shared/components/TaskActionMenu';
import { getFieldValue } from '@shared/utils/fieldUtils';

type HeadingLevel = 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
type TagsPosition = 'top' | 'bottom';

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
	descriptionMaxLength = 70,
	tagsPosition = 'bottom',
	showOrder = true,
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
		if (!dueDate) {
			return null;
		}

		return <span className="task-card-due-date">{dueDate}</span>;
	};

	const renderAssociatedOrder = () => {
		const orderValue = getFieldValue(task, 'order');

		if (
			!orderValue ||
			typeof orderValue !== 'object' ||
			Array.isArray(orderValue)
		) {
			return null;
		}

		const order = orderValue as Record<string, unknown>;

		return (
			<div className="task-card-order">
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
				<div className="task-card-header-info">
					{/* Tags for compact mode */}
					{tagsPosition === 'top' && renderTags()}
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
			{showDescription && renderDescription()}

			<div className="task-card-footer">
				<div className="task-card-info">
					{tagsPosition === 'bottom' && renderTags()}
					{showOrder && renderAssociatedOrder()}
				</div>
				{renderDueDate()}
			</div>
		</div>
	);
};
