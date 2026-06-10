import {useEffect, useState} from 'react';
import { __ } from '@wordpress/i18n';
import {
	fetchCustomOrderStatuses,
	createCustomOrderStatus,
	updateCustomOrderStatus,
	deleteCustomOrderStatus,
} from '@shared/utils/api';
import { CustomOrderStatus } from '@shared/types/customOrderStatus';
import {
	useAsyncLoader,
	AsyncLoaderStatus,
} from '@shared/hooks/useAsyncLoader';
import { useToast, ToastType } from '@shared/context/ToastContext';

/** Fields editable through the create/edit form. */
export type CustomOrderStatusInput = Pick<
	CustomOrderStatus,
	'label' | 'status_key' | 'background'
>;

export interface UseCustomOrderStatusesResult {
	statuses: CustomOrderStatus[];
	loadingStatus: AsyncLoaderStatus;
	loadingError: Error | null;
	isSaving: boolean;
	deletingId: number | null;
	createStatus: (data: CustomOrderStatusInput) => Promise<void>;
	updateStatus: (id: number, data: CustomOrderStatusInput) => Promise<void>;
	deleteStatus: (id: number) => Promise<void>;
}

/**
 * Owns the custom-order-status collection and its CRUD operations: initial
 * load, create, update and delete, plus the in-flight flags those mutations
 * expose. UI/mode state (which row is being edited, whether the create form is
 * open) stays in the view — this hook is only concerned with the data.
 */
export function useCustomOrderStatuses(): UseCustomOrderStatusesResult {
	const [statuses, setStatuses] = useState<CustomOrderStatus[]>([]);
	const [isSaving, setIsSaving] = useState(false);
	const { addToast } = useToast();

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		const data = await fetchCustomOrderStatuses();
		setStatuses(data);
	});

	const reportError = (error: unknown, title: string): void => {
		console.error(title, error);
		addToast({
			title,
			message:
				error instanceof Error
					? error.message
					: __(
							'Something went wrong. Please try again.',
							'wpo-advanced-order-manager'
						),
			type: ToastType.ERROR,
		});
	};

	const createStatus = async (
		data: CustomOrderStatusInput
	): Promise<void> => {
		setIsSaving(true);
		try {
			const created = await createCustomOrderStatus(data);
			setStatuses((prev) => [...prev, created]);
		} catch (error) {
			reportError(
				error,
				__('Could not create status', 'wpo-advanced-order-manager')
			);
			throw error;
		} finally {
			setIsSaving(false);
		}
	};

	const updateStatus = async (
		id: number,
		data: CustomOrderStatusInput
	): Promise<void> => {
		setIsSaving(true);
		try {
			const updated = await updateCustomOrderStatus(id, data);
			setStatuses((prev) =>
				prev.map((status) => (status.id === id ? updated : status))
			);
		} catch (error) {
			reportError(
				error,
				__('Could not update status', 'wpo-advanced-order-manager')
			);
			throw error;
		} finally {
			setIsSaving(false);
		}
	};

	const deleteStatus = async (id: number): Promise<void> => {
		setDeletingId(id);
		try {
			// Deletion is async server-side: the request only schedules the
			// order drain, so flip the row to its "deleting" state rather than
			// removing it. It disappears once the drain finishes (handled by the
			// poll, added separately). The flip only runs on success, so a
			// failure leaves the row untouched.
			await deleteCustomOrderStatus(id);
			setStatuses((prev) =>
				prev.map((status) =>
					status.id === id
						? { ...status, is_deleting: true }
						: status
				)
			);
		} catch (error) {
			reportError(
				error,
				__('Could not delete status', 'wpo-advanced-order-manager')
			);
			throw error;
		}
	};

	return {
		statuses,
		loadingStatus,
		loadingError,
		isSaving,
		createStatus,
		updateStatus,
		deleteStatus,
	};
}