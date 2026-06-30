import React, { useRef } from 'react';
import { __ } from '@wordpress/i18n';

import { useTasks } from '@shared/context/TaskContext';

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
	const { createFieldOption } = useTasks();
	const inputRef = useRef<HTMLInputElement | null>(null);

	const handleSave = () => {
		const label = inputRef.current?.value.trim();
		if (!label) {
			onClose();
			return;
		}

		void createFieldOption(fieldId, {
			label,
			field_id: fieldId,
			position,
		}).finally(onClose);
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
								'wpo-advanced-order-manager'
							)}
							className="edit-title-input"
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
									{__('Cancel', 'wpo-advanced-order-manager')}
								</span>
							</button>
							<span className="wpo-aom-vertical-divider" />
							<button
								type="button"
								onClick={handleSave}
								className="wpo-button wpo-button-icon save-title-button"
							>
								<span className="screen-reader-text">
									{__('Save', 'wpo-advanced-order-manager')}
								</span>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	);
};
