import React from 'react';
import { __ } from '@wordpress/i18n';
import { CustomOrderStatus } from '@sdk';
import { StatusForm } from './StatusForm';

interface StatusTableProps {
	statuses: CustomOrderStatus[];
	editingId: number | null;
	isCreating: boolean;
	isSaving: boolean;
	pendingDeletionIds: Set<number>;
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
	isCreating,
	isSaving,
	pendingDeletionIds,
	onEdit,
	onCancelEdit,
	onSaveEdit,
	onSaveCreate,
	onCancelCreate,
	onDelete,
	onStartCreate,
}: StatusTableProps) => {
	const isDeleting = (status: CustomOrderStatus) => status.is_deleting;
	const isBusy = (status: CustomOrderStatus) =>
		status.is_deleting || pendingDeletionIds.has(status.id);

	return (
		<>
			<table className="cos-table">
				<thead>
					<tr>
						<th id="label-header">
							{__('Label', 'out-the-door-order-tasks-workflows-for-woocommerce')}
						</th>
						<th id="color-header">
							{__('Color', 'out-the-door-order-tasks-workflows-for-woocommerce')}
						</th>
						<th id="slug-header">
							{__('Slug', 'out-the-door-order-tasks-workflows-for-woocommerce')}
						</th>
						<th id="actions-header">
							{__('Actions', 'out-the-door-order-tasks-workflows-for-woocommerce')}
						</th>
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
									isBusy(status) ? 'deleting-row' : undefined
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
								<td
									className={
										isBusy(status)
											? 'slug-column deleting'
											: 'slug-column'
									}
								>
									{isBusy(status) && (
										<>
											<span className="wpo-aom-loader"></span>
											<span className="deleting-notice">
												{isDeleting(status)
													? __(
															'Reassigning order statuses before deletion.',
															'out-the-door-order-tasks-workflows-for-woocommerce'
														)
													: __(
															'Deleting…',
															'out-the-door-order-tasks-workflows-for-woocommerce'
														)}
											</span>
										</>
									)}
									{!isBusy(status) && (
										<span className="slug-field">
											{status.status_key}
										</span>
									)}
								</td>
								<td className="actions-column">
									{!isBusy(status) && (
										<ul className="wpo-aom-row-actions">
											<li>
												<button
													type="button"
													className="wpo-button wpo-button-icon wpo-aom-edit-button"
													onClick={() =>
														onEdit(status.id)
													}
													title={__(
														'Edit',
														'out-the-door-order-tasks-workflows-for-woocommerce'
													)}
													disabled={isBusy(status)}
												>
													<span className="screen-reader-text">
														{__(
															'Edit',
															'out-the-door-order-tasks-workflows-for-woocommerce'
														)}
													</span>
												</button>
											</li>
											<li>
												<button
													type="button"
													className="wpo-button wpo-button-icon wpo-aom-delete-button"
													onClick={() =>
														onDelete(status.id)
													}
													title={__(
														'Delete',
														'out-the-door-order-tasks-workflows-for-woocommerce'
													)}
													disabled={isBusy(status)}
												>
													<span className="screen-reader-text">
														{__(
															'Delete',
															'out-the-door-order-tasks-workflows-for-woocommerce'
														)}
													</span>
												</button>
											</li>
										</ul>
									)}
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
					{__('Add new status', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</button>
			)}
		</>
	);
};
