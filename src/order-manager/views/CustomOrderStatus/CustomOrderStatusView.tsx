import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { ErrorState, EmptyState, SkeletonLine, useConfirm } from '@sdk';
import { StatusTable } from './components/StatusTable';
import {
	useCustomOrderStatuses,
	CustomOrderStatusInput,
} from './hooks/useCustomOrderStatuses';

export const CustomOrderStatusView = () => {
	const [editingId, setEditingId] = useState<number | null>(null);
	const [isCreating, setIsCreating] = useState(false);
	const confirm = useConfirm();

	const {
		statuses,
		loadingStatus,
		loadingError,
		isSaving,
		pendingDeletionIds,
		createStatus,
		updateStatus,
		deleteStatus,
	} = useCustomOrderStatuses();

	const handleCreate = async (data: CustomOrderStatusInput) => {
		try {
			await createStatus(data);
			setIsCreating(false);
		} catch {
			// Already reported via toast in the hook.
		}
	};

	const handleUpdate = async (id: number, data: CustomOrderStatusInput) => {
		try {
			await updateStatus(id, data);
			setEditingId(null);
		} catch {
			// Already reported via toast in the hook.
		}
	};

	const handleDelete = async (id: number) => {
		const confirmationResult = await confirm({
			title: __('Delete this status?', 'out-the-door-order-tasks-workflows-for-woocommerce'),
			message: __(
				'Are you sure you want to delete this status? Orders with this status will be moved to On Hold.',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			confirmText: __('Delete', 'out-the-door-order-tasks-workflows-for-woocommerce'),
			cancelText: __('Cancel', 'out-the-door-order-tasks-workflows-for-woocommerce'),
			action: 'delete',
		});

		if (!confirmationResult) {
			return;
		}

		try {
			await deleteStatus(id);
		} catch {
			// Already reported via toast in the hook.
		}
	};

	if (loadingStatus === 'loading') {
		return (
			<div className="custom-order-status-view">
				<h3>{__('Active Statuses', 'out-the-door-order-tasks-workflows-for-woocommerce')}</h3>
				<div className="cos-skeleton">
					<SkeletonLine width="100%" height="2.5em" />
					<SkeletonLine width="100%" height="2.5em" />
					<SkeletonLine width="100%" height="2.5em" />
					<SkeletonLine width="100%" height="2.5em" />
				</div>
			</div>
		);
	}

	if (loadingStatus === 'error') {
		return (
			<div className="custom-order-status-view">
				<ErrorState
					message={
						loadingError?.message ||
						__(
							'Error loading custom order statuses. Please try again.',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)
					}
				/>
			</div>
		);
	}

	return (
		<>
			<h2 className="screen-reader-text">
				{__('Custom Order Statuses', 'out-the-door-order-tasks-workflows-for-woocommerce')}
			</h2>
			<div className="custom-order-status-view">
				<h3>{__('Active Statuses', 'out-the-door-order-tasks-workflows-for-woocommerce')}</h3>
				{statuses.length === 0 && !isCreating ? (
					<EmptyState
						icon="🏷"
						message={__(
							'No custom order statuses yet.',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)}
						actionText={__(
							'Add new status',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)}
						onAction={() => setIsCreating(true)}
					/>
				) : (
					<StatusTable
						statuses={statuses}
						editingId={editingId}
						isCreating={isCreating}
						isSaving={isSaving}
						pendingDeletionIds={pendingDeletionIds}
						onEdit={(id) => {
							setEditingId(id);
							setIsCreating(false);
						}}
						onCancelEdit={() => setEditingId(null)}
						onSaveEdit={handleUpdate}
						onSaveCreate={handleCreate}
						onCancelCreate={() => setIsCreating(false)}
						onDelete={handleDelete}
						onStartCreate={() => {
							setIsCreating(true);
							setEditingId(null);
						}}
					/>
				)}
			</div>
		</>
	);
};
