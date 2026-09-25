import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	fetchCustomOrderStatuses,
	createCustomOrderStatus,
	updateCustomOrderStatus,
	deleteCustomOrderStatus,
	CustomOrderStatus,
	useAsyncLoader,
	AsyncLoaderStatus,
	useToast,
	ToastType,
} from '@sdk';

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
	pendingDeletionIds: Set<number>;
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
	const [pendingDeletionIds, setPendingDeletionIds] = useState<Set<number>>(
		new Set()
	);
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
							'out-the-door-order-tasks-workflows-for-woocommerce'
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
				__('Could not create status', 'out-the-door-order-tasks-workflows-for-woocommerce')
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
				__('Could not update status', 'out-the-door-order-tasks-workflows-for-woocommerce')
			);
			throw error;
		} finally {
			setIsSaving(false);
		}
	};

	const deleteStatus = async (id: number): Promise<void> => {
		setPendingDeletionIds((prev) => new Set(prev).add(id));

		try {
			await deleteCustomOrderStatus(id);

			// Deletion resolves two ways server-side: immediately, when the
			// status has no orders to reassign, or asynchronously, when it
			// schedules an order drain and flags the row is_deleting. Re-fetch so
			// the UI reflects whichever happened — the row either disappears or
			// returns in its "deleting" state, and the poll below drives it to
			// completion. This only runs on success, so a failure leaves the row
			// untouched.
			const fresh = await fetchCustomOrderStatuses();
			setStatuses(fresh);
		} catch (error) {
			reportError(
				error,
				__('Could not delete status', 'out-the-door-order-tasks-workflows-for-woocommerce')
			);
			throw error;
		} finally {
			setPendingDeletionIds((prev) => {
				const next = new Set(prev);
				next.delete(id);
				return next;
			});
		}
	};

	const hasDeleting = statuses.some((s) => s.is_deleting);

	useEffect(() => {
		if (!hasDeleting) {
			return;
		}

		const id = setInterval(async () => {
			try {
				const fresh = await fetchCustomOrderStatuses();
				setStatuses(fresh);
			} catch (error) {
				console.error(
					'Failed to refresh statuses during deletion:',
					error
				);
			}
		}, 5000);

		return () => clearInterval(id);
	}, [hasDeleting]);

	return {
		statuses,
		loadingStatus,
		loadingError,
		isSaving,
		pendingDeletionIds,
		createStatus,
		updateStatus,
		deleteStatus,
	};
}
