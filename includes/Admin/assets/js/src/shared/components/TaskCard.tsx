import React from 'react';
import { isFieldOption, Task } from '@shared/types/task';
import { getColorStyle } from '@shared/utils/colorUtils';

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
	i18n: {
		options: string;
		edit: string;
		delete: string;
		dueDateLabel: string;
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

	return (
		<div
			ref={innerRef}
			className={`kanban-card ${className} ${isSelected ? 'selected' : ''}`}
			onClick={handleCardClick}
			onKeyDown={(e) => {
				if (e.key === 'Enter' || e.key === ' ') {
					handleCardClick();
				}
			}}
		>
			<div className="kanban-card-header">
				<h3>{task.title}</h3>
				{onOptionsClick && (
					<div className="kanban-card-options">
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
											className="wpo-button card-edit-button"
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
											className="wpo-button card-delete-button"
											onClick={onDeleteClick}
										>
											{i18n.delete}
										</button>
									</li>
								)}
							</ul>
						)}
					</div>
				)}
			</div>
			<ul className="kanban-card-tags">
				{priorityValue && isFieldOption(priorityValue) && (
					<li
						className={`wpo-tag kanban-card-priority priority-${priorityValue?.slug}`}
						style={getColorStyle(priorityValue.color)}
					>
						{priorityValue.label}
					</li>
				)}
			</ul>
			{dueDateValue && (
				<span className="kanban-card-due-date">
					{i18n.dueDateLabel}: {dueDate}
				</span>
			)}
		</div>
	);
};
