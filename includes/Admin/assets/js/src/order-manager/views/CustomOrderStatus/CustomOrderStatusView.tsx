import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';
import {
	fetchCustomOrderStatuses,
	createCustomOrderStatus,
	updateCustomOrderStatus,
	deleteCustomOrderStatus,
} from '@shared/utils/api';
import { CustomOrderStatus } from '@shared/types/customOrderStatus';
import {
	ErrorState,
	EmptyState,
	SkeletonLine,
} from '@shared/components/LoadingSkeleton';
import { StatusTable } from './components/StatusTable';
import { useConfirm } from '@shared/context/DialogContext';

export const CustomOrderStatusView = () => {
	const [statuses, setStatuses] = useState<CustomOrderStatus[]>([]);
	const [editingId, setEditingId] = useState<number | null>(null);
	const [isCreating, setIsCreating] = useState(false);
	const [isSaving, setIsSaving] = useState(false);
	const [deletingId, setDeletingId] = useState<number | null>(null);
	const confirm = useConfirm();

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		const data = await fetchCustomOrderStatuses();
		setStatuses(data);
	});

	const handleCreate = async (data: {
		label: string;
		status_key: string;
		background: string;
	}) => {
		setIsSaving(true);
		try {
			const created = await createCustomOrderStatus(data);
			setStatuses((prev) => [...prev, created]);
			setIsCreating(false);
		} finally {
			setIsSaving(false);
		}
	};

	const handleUpdate = async (
		id: number,
		data: { label: string; status_key: string; background: string }
	) => {
		setIsSaving(true);
		try {
			const updated = await updateCustomOrderStatus(id, data);
			setStatuses((prev) => prev.map((s) => (s.id === id ? updated : s)));
			setEditingId(null);
		} finally {
			setIsSaving(false);
		}
	};

	const handleDelete = async (id: number) => {
		const confirmationResult = await confirm({
			title: __('Delete this status?', 'wpo-advanced-order-manager'),
			message: __(
				'Are you sure you want to delete this status? Orders with this status will be moved to On Hold.',
				'wpo-advanced-order-manager'
			),
			confirmText: __('Delete', 'wpo-advanced-order-manager'),
			cancelText: __('Cancel', 'wpo-advanced-order-manager'),
			action: 'delete',
		});

		if (!confirmationResult) {
			return;
		}

		setDeletingId(id);
		try {
			await deleteCustomOrderStatus(id);
			setStatuses((prev) => prev.filter((s) => s.id !== id));
		} finally {
			setDeletingId(null);
		}
	};

	if (loadingStatus === 'loading') {
		return (
			<div className="custom-order-status-view">
				<h3>{__('Active Statuses', 'wpo-advanced-order-manager')}</h3>
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
							'wpo-advanced-order-manager'
						)
					}
				/>
			</div>
		);
	}

	return (
		<>
			<h2 className="screen-reader-text">
				{__('Custom Order Statuses', 'wpo-advanced-order-manager')}
			</h2>
			<div className="custom-order-status-view">
				<h3>{__('Active Statuses', 'wpo-advanced-order-manager')}</h3>
				{statuses.length === 0 && !isCreating ? (
					<EmptyState
						icon="🏷"
						message={__('No custom order statuses yet.', 'wpo-advanced-order-manager')}
						actionText={__('Add new status', 'wpo-advanced-order-manager')}
						onAction={() => setIsCreating(true)}
					/>
				) : (
					<StatusTable
						statuses={statuses}
						editingId={editingId}
						deletingId={deletingId}
						isCreating={isCreating}
						isSaving={isSaving}
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
