import React from 'react';
import { isFieldOption, Task } from '@shared/types/task';
import { getColorStyle } from '@shared/utils/colorUtils';
import { truncateText } from '@shared/utils/textUtils';

type ActionsDisplayMode = 'menu' | 'icons' | 'none';
type HeadingLevel = 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

interface TaskCardProps {
	task: Task;
	isOptionsOpen?: boolean;
	isSelected?: boolean;
	onCardClick?: () => void;
	onOptionsClick?: (e: React.MouseEvent) => void;
	onEditClick?: (e: React.MouseEvent) => void;
	onDeleteClick?: (e: React.MouseEvent) => void;
	className?: string;
	innerRef?: React.RefObject<HTMLDivElement | null>;
	actionsDisplayMode?: ActionsDisplayMode;
	headingLevel?: HeadingLevel;
	showDescription?: boolean;
	isCompact?: boolean;
	i18n: {
		options?: string;
		edit?: string;
		delete?: string;
	};
}

export const TaskCard: React.FC<TaskCardProps> = ({
	task,
	isOptionsOpen = false,
	isSelected = false,
	onCardClick,
	onOptionsClick,
	onEditClick,
	onDeleteClick,
	className = '',
	innerRef,
	actionsDisplayMode = 'menu',
	headingLevel = 'h3',
	showDescription = false,
	isCompact = false,
	i18n,
}) => {
	const priorityField = task.fields?.find(
		(field) => field.slug === 'priority'
	);
	const dueDateField = task.fields?.find(
		(field) => field.slug === 'due-date'
	);

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

	const handleCardClick = () => {
		if (onCardClick) {
			onCardClick();
		}
	};

	const renderMenuActions = () => (
		<div className="task-card-options">
			<button
				className="wpo-button wpo-button-icon wpo-options-button"
				type="button"
				onClick={onOptionsClick}
			>
				<span className="screenReader">{i18n.options}</span>
			</button>
			{isOptionsOpen && (
				<ul className="wpo-action-menu">
					{onEditClick && (
						<li>
							<button
								type="button"
								className="wpo-button task-edit-menu-item"
								onClick={onEditClick}
							>
								{i18n.edit}
							</button>
						</li>
					)}
					{onDeleteClick && (
						<li>
							<button
								type="button"
								className="wpo-button task-delete-menu-item"
								onClick={onDeleteClick}
							>
								{i18n.delete}
							</button>
						</li>
					)}
				</ul>
			)}
		</div>
	);

	const renderIconActions = () => (
		<ul className="wpo-aom-task-actions task-card-actions">
			{onEditClick && (
				<li>
					<button
						className="wpo-button wpo-button-icon task-edit"
						type="button"
						onClick={onEditClick}
						title={i18n.edit}
					>
						<span className="screenReader">{i18n.edit}</span>
					</button>
				</li>
			)}
			{onDeleteClick && (
				<li>
					<button
						className="wpo-button wpo-button-icon task-delete"
						type="button"
						onClick={onDeleteClick}
						title={i18n.delete}
					>
						<span className="screenReader">{i18n.delete}</span>
					</button>
				</li>
			)}
		</ul>
	);

	const hasActions = onEditClick || onDeleteClick || onOptionsClick;

	// Create dynamic heading element
	const HeadingTag = headingLevel;

	const renderInfo = () => {
		return (
			<>
				<ul className="task-card-tags">
					{priorityValue && isFieldOption(priorityValue) && (
						<li
							className={`wpo-aom-tag task-card-priority priority-${priorityValue?.slug}`}
							style={getColorStyle(priorityValue.color)}
						>
							{priorityValue.label}
						</li>
					)}
				</ul>
				{dueDate && (
					<span className="task-card-due-date">{dueDate}</span>
				)}
			</>
		);
	};

	return (
		<div
			ref={innerRef}
			className={`task-card ${isCompact ? 'compact': ''} ${className} ${isSelected ? 'selected' : ''}`}
			onClick={handleCardClick}
			onKeyDown={(e) => {
				if (e.key === 'Enter' || e.key === ' ') {
					handleCardClick();
				}
			}}
		>
			<div className="task-card-header">
				<HeadingTag>{task.title}</HeadingTag>
				{isCompact && (
                    <div className="task-card-info">{renderInfo()}</div>
                )}
				{!isCompact &&
					hasActions &&
					(actionsDisplayMode === 'menu'
						? renderMenuActions()
						: actionsDisplayMode === 'icons'
							? renderIconActions()
							: null)}
			</div>
			{showDescription && task.description && (
				<div className="task-card-description">
					<p>{truncateText(task.description, 100)}</p>
				</div>
			)}
			{!isCompact && (
				<div className="task-card-footer">{renderInfo()}</div>
			)}
		</div>
	);
};
