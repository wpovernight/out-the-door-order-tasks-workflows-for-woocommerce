import React, { useRef } from 'react';
import { __ } from '@wordpress/i18n';

import { useTasks } from '@shared/context/TaskContext';
import { ToastType, useToast } from '@shared/context/ToastContext';

interface DraftColumnProps {
	fieldId: number;
	position: number;
	onClose: () => void;
}

export const DraftColumn: React.FC<DraftColumnProps> = ({
	fieldId,
	position,
	onClose,
}) => {
	const { createFieldOption, fieldOptions } = useTasks();
	const { addToast } = useToast();
	const inputRef = useRef<HTMLInputElement | null>(null);

	const handleSave = () => {
		const label = inputRef.current?.value.trim();
		if (!label) {
			onClose();
			return;
		}

		// Reject a name that already belongs to another column.
		const isDuplicate = Object.values(fieldOptions)
			.flat()
			.some(
				(option) =>
					option.field_id === fieldId &&
					option.label.trim().toLowerCase() === label.toLowerCase()
			);

		if (isDuplicate) {
			addToast({
				title: __(
					'A column with this name already exists.',
					'advanced-order-manager'
				),
				message: __(
					'Please choose a different name.',
					'advanced-order-manager'
				),
				type: ToastType.ERROR,
			});
			inputRef.current?.select();
			return;
		}

		createFieldOption(fieldId, {
			label,
			field_id: fieldId,
			position,
		})
			.then(() => onClose())
			.catch((error) => {
				addToast({
					title: __(
						'Failed to create column.',
						'advanced-order-manager'
					),
					message:
						error instanceof Error && error.message
							? error.message
							: __(
									'Please try again.',
									'advanced-order-manager'
								),
					type: ToastType.ERROR,
				});
				inputRef.current?.select();
			});
	};

	return (
		<div className="kanban-column kanban-column-draft">
			<div className="kanban-column-inner">
				<div className="kanban-column-header">
					<div className="kanban-column-header-title">
						<input
							ref={inputRef}
							type="text"
							defaultValue={__(
								'New Column',
								'advanced-order-manager'
							)}
							className="edit-title-input"
							// eslint-disable-next-line jsx-a11y/no-autofocus
							autoFocus
							onFocus={(e) => e.currentTarget.select()}
							onKeyDown={(e) => {
								if (e.key === 'Enter') {
									e.preventDefault();
									handleSave();
								} else if (e.key === 'Escape') {
									e.preventDefault();
									onClose();
								}
							}}
						/>
						<div className="edit-title-actions">
							<button
								type="button"
								onClick={onClose}
								className="wpo-button wpo-button-icon cancel-edit-title-button"
							>
								<span className="screen-reader-text">
									{__('Cancel', 'advanced-order-manager')}
								</span>
							</button>
							<span className="wpo-aom-vertical-divider" />
							<button
								type="button"
								onClick={handleSave}
								className="wpo-button wpo-button-icon save-title-button"
							>
								<span className="screen-reader-text">
									{__('Save', 'advanced-order-manager')}
								</span>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	);
};
