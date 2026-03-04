import React from 'react';
import { __ } from '@wordpress/i18n';
import { CustomOrderStatus } from '@shared/types/customOrderStatus';
import { StatusForm } from './StatusForm';

interface StatusTableProps {
	statuses: CustomOrderStatus[];
	editingId: number | null;
	deletingId: number | null;
	isCreating: boolean;
	isSaving: boolean;
	onEdit: (id: number) => void;
	onCancelEdit: () => void;
	onSaveEdit: (
		id: number,
		data: { label: string; status_key: string; background: string }
	) => Promise<void>;
	onSaveCreate: (data: {
		label: string;
		status_key: string;
		background: string;
	}) => Promise<void>;
	onCancelCreate: () => void;
	onDelete: (id: number) => void;
	onStartCreate: () => void;
}

export const StatusTable = ({
	statuses,
	editingId,
	deletingId,
	isCreating,
	isSaving,
	onEdit,
	onCancelEdit,
	onSaveEdit,
	onSaveCreate,
	onCancelCreate,
	onDelete,
	onStartCreate,
}: StatusTableProps) => {
	return (
		<>
			<table className="cos-table">
				<thead>
					<tr>
						<th id="label-header">{__('Label', 'wpo-aom')}</th>
						<th id="color-header">{__('Color', 'wpo-aom')}</th>
						<th id="slug-header">{__('Slug', 'wpo-aom')}</th>
						<th id="actions-header">{__('Actions', 'wpo-aom')}</th>
					</tr>
				</thead>
				<tbody>
					{statuses.map((status) =>
						editingId === status.id ? (
							<StatusForm
								key={status.id}
								status={status}
								onSave={(data) => onSaveEdit(status.id, data)}
								onCancel={onCancelEdit}
								isSaving={isSaving}
							/>
						) : (
							<tr
								key={status.id}
								className={
									deletingId === status.id
										? 'deleting-row'
										: undefined
								}
							>
								<td className="label-column">
									<div>
										{status.label}
										<span
											className="status-preview"
											style={{
												backgroundColor:
													status.background,
												color: status.foreground,
											}}
										>
											{status.label}
										</span>
									</div>
								</td>
								<td className="color-column">
									<span
										className="color-swatch"
										style={{
											backgroundColor: status.background,
										}}
									/>
									<span className="color-hex">
										{status.background.toUpperCase()}
									</span>
								</td>
								<td className="slug-column">
									<span className="slug-field">
										{status.status_key}
									</span>
								</td>
								<td className="actions-column">
									<ul className="wpo-aom-row-actions">
										<li>
											<button
												type="button"
												className="wpo-button wpo-button-icon wpo-aom-edit-button"
												onClick={() =>
													onEdit(status.id)
												}
												title={__('Edit', 'wpo-aom')}
											>
												<span className="screenReader">
													{__('Edit', 'wpo-aom')}
												</span>
											</button>
											<button
												type="button"
												className="wpo-button wpo-button-icon wpo-aom-delete-button"
												onClick={() =>
													onDelete(status.id)
												}
												title={__('Delete', 'wpo-aom')}
											>
												<span className="screenReader">
													{__('Delete', 'wpo-aom')}
												</span>
											</button>
										</li>
									</ul>
								</td>
							</tr>
						)
					)}
					{isCreating && (
						<StatusForm
							onSave={onSaveCreate}
							onCancel={onCancelCreate}
							isSaving={isSaving}
						/>
					)}
				</tbody>
			</table>
			{!isCreating && (
				<button
					type="button"
					id="add-new-status"
					className="wpo-button wpo-button-primary"
					onClick={onStartCreate}
				>
					{__('Add new status', 'wpo-aom')}
				</button>
			)}
		</>
	);
};
