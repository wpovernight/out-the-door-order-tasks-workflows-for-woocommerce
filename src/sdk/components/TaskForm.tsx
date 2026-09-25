import React, { useCallback, useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useTasks } from '@sdk/context/TaskContext';
import { useAsyncLoader } from '@sdk/hooks/useAsyncLoader';
import { FieldOptionDropdown } from '@sdk/components/FieldOptionDropdownField';
import { AsyncMultiSelectField } from '@sdk/components/AsyncMultiSelectField';
import { TaskFormSkeleton } from '@sdk/components/TaskFormSkeleton';
import { searchOrders } from '@sdk/utils/api';
import { isFieldOption, Task } from '@sdk/types/task';
import { useSidebarModal } from '@sdk/context/SidebarModalContext';
import { useConfirm } from '@sdk/context/DialogContext';
import { ToastType, useToast } from '@sdk/context/ToastContext';
import { DatePicker } from '@sdk/components/DatePicker';

export interface TaskFormInitialValues {
	title?: string;
	description?: string;
	dueDate?: string;
	statusIndex?: number;
	priorityIndex?: number;
	orderIds?: number[];
}

interface TaskFormProps {
	task?: Task;
	initialValues?: TaskFormInitialValues;
	onDone?: () => void;
	onTaskSaved?: (task: Task) => void;
}

export const TaskForm: React.FC<TaskFormProps> = ({
	task,
	initialValues,
	onDone,
	onTaskSaved,
}) => {
	const {
		saveTask,
		loadTaskFields,
		taskFields,
		fieldOptions,
		loadFieldOptions,
	} = useTasks();
	const { setBeforeClose } = useSidebarModal();
	const confirm = useConfirm();
	const { addToast } = useToast();

	const [isSubmitting, setIsSubmitting] = useState(false);
	// Tracks unsaved edits so the close guard knows whether to prompt before closing.
	const isDirtyRef = useRef(false);
	// Tracks whether a submission is in progress so the close guard can prevent closing during that time.
	const isSubmittingRef = useRef(false);

	const beforeCloseGuard = useCallback(async () => {
		if (isSubmittingRef.current) {
			return false;
		}

		if (!isDirtyRef.current) {
			return true;
		}

		return await confirm({
			title: __('Save your changes?', 'out-the-door-order-tasks-workflows-for-woocommerce'),
			message: __(
				'You have unsaved work. Discarding will permanently erase your recent edits.',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
			confirmText: __('Discard', 'out-the-door-order-tasks-workflows-for-woocommerce'),
			cancelText: __('Keep editing', 'out-the-door-order-tasks-workflows-for-woocommerce'),
			action: 'save',
			invertActions: true,
		});
	}, [confirm]);

	useEffect(() => {
		setBeforeClose(beforeCloseGuard);
		return () => setBeforeClose(null);
	}, [setBeforeClose, beforeCloseGuard]);

	const handleFormChange = () => {
		isDirtyRef.current = true;
	};
	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([
			loadTaskFields(),
			loadFieldOptions('status'),
			loadFieldOptions('priority'),
		]);
	}, [loadFieldOptions, loadTaskFields]);

	const prepareFormData = (formData: FormData): Record<string, any> => {
		const fieldValues: Array<{
			field_id: number;
			field_slug: string;
			value: string | string[] | number;
		}> = [];

		for (const fieldSlug of Object.keys(taskFields)) {
			const arrayInputs = ['order'];
			const fieldSlugFormatted = fieldSlug.replace(/-/g, '_');
			const field = taskFields[fieldSlug];
			const formFieldName = `field_${fieldSlugFormatted}`;

			// For multiple value fields, try with [] suffix first
			let rawValue: FormDataEntryValue[];
			if (arrayInputs.includes(fieldSlug)) {
				rawValue = formData.getAll(`${formFieldName}[]`);
				// Fallback to without [] if nothing found
				if (rawValue.length === 0) {
					rawValue = formData.getAll(formFieldName);
				}
			} else {
				rawValue = formData.getAll(formFieldName);
			}

			if (rawValue.length === 0) {
				continue; // Skip if no value provided
			}

			let value: any;
			if (arrayInputs.includes(fieldSlug)) {
				value = rawValue;
			} else {
				value = rawValue[0];
			}

			if (value !== null) {
				fieldValues.push({
					field_id: field.id,
					field_slug: field.slug,
					value,
				});
			}
		}

		return {
			title: formData.get('title'),
			description: formData.get('description'),
			field_values: fieldValues,
		};
	};

	const submit = async (e: React.SubmitEvent) => {
		e.preventDefault();
		if (isSubmitting) {
			return; // Prevent multiple submissions
		}
		setIsSubmitting(true);
		isSubmittingRef.current = true;

		const isUpdate = Boolean(task?.id);

		try {
			const form = e.target as HTMLFormElement;
			const formData = new FormData(form);
			const payload = prepareFormData(formData);

			const savedTask = await saveTask(payload, task?.id);
			isDirtyRef.current = false;
			// Clear before onDone() so the programmatic close below passes the
			// guard instead of being blocked by the in-flight check.
			isSubmittingRef.current = false;
			onTaskSaved?.(savedTask);
			addToast({
				title: isUpdate
					? __(
							'The task has been successfully updated.',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)
					: __(
							'A new task has been successfully created.',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						),
				type: ToastType.SUCCESS,
			});
			onDone?.();
		} catch (error) {
			console.error('Failed to save task:', error);
			addToast({
				title: isUpdate
					? __('Failed to update task.', 'out-the-door-order-tasks-workflows-for-woocommerce')
					: __('Failed to create task.', 'out-the-door-order-tasks-workflows-for-woocommerce'),
				message:
					error instanceof Error && error.message
						? error.message
						: __('Please try again.', 'out-the-door-order-tasks-workflows-for-woocommerce'),
				type: ToastType.ERROR,
			});
		} finally {
			setIsSubmitting(false);
			// Safety net for the failure path (the success path already cleared
			// it before closing), so later close attempts prompt normally.
			isSubmittingRef.current = false;
		}
	};

	// Check if data is actually loaded
	const isDataReady =
		Object.keys(taskFields).length > 0 &&
		fieldOptions.status?.length > 0 &&
		fieldOptions.priority?.length > 0;

	if (loadingStatus === 'loading' || !isDataReady) {
		return <TaskFormSkeleton />;
	}

	if (loadingStatus === 'error') {
		return (
			<div className="error-message">
				{__(
					'Error loading data. Please try again.',
					'out-the-door-order-tasks-workflows-for-woocommerce'
				)}
			</div>
		);
	}

	const statusResolved = task
		? task.fields.find((field) => field.slug === 'status')?.values?.[0]
				?.resolved
		: null;
	const defaultStatusIndex = initialValues?.statusIndex ?? 0;
	const statusOption =
		statusResolved && isFieldOption(statusResolved)
			? statusResolved
			: fieldOptions?.status?.[defaultStatusIndex];

	const priorityResolved = task
		? task.fields.find((field) => field.slug === 'priority')?.values?.[0]
				?.resolved
		: null;
	const defaultPriorityIndex = initialValues?.priorityIndex ?? 0;
	const priorityOption =
		priorityResolved && isFieldOption(priorityResolved)
			? priorityResolved
			: fieldOptions?.priority?.[defaultPriorityIndex];

	const dueDate = task
		? (task.fields.find((field) => field.slug === 'due_date')?.values?.[0]
				?.raw as string)
		: initialValues?.dueDate;

	const associatedOrders = task?.fields.find(
		(field) => field.slug === 'order'
	)?.values;
	let associatedOrderIds = associatedOrders
		?.map((v) => {
			const raw = v?.raw;
			if (raw === null || raw === '') {
				return null;
			}
			const num = Number(raw);
			if (Number.isNaN(num) || num === 0) {
				// `0` is the sentinel for "no order assigned".
				return null;
			}
			return { id: num, label: `#${raw}` };
		})
		.filter((item): item is { id: number; label: string } => item !== null);

	// If creating a new task and orderIds are provided, pre-select them
	if (!task && initialValues?.orderIds?.length) {
		associatedOrderIds = initialValues.orderIds.map((id) => ({
			id,
			label: `#${id}`,
		}));
	}

	// The form field name should follow the pattern: field_{field_slug}
	return (
		<form
			onSubmit={submit}
			onChange={handleFormChange}
			className={`wpo-aom-task-form ${isSubmitting ? 'submitting' : ''}`}
		>
			<fieldset disabled={isSubmitting}>
				<div className="field-group">
					<div>
						<label htmlFor="status">
							{__('Status', 'out-the-door-order-tasks-workflows-for-woocommerce')}
						</label>
						<FieldOptionDropdown
							placeholder={__('Select', 'out-the-door-order-tasks-workflows-for-woocommerce')}
							options={fieldOptions.status}
							id="status"
							name="field_status"
							selected={statusOption}
							onChange={handleFormChange}
						/>
					</div>
					<div>
						<label htmlFor="priority">
							{__('Priority', 'out-the-door-order-tasks-workflows-for-woocommerce')}
						</label>
						<FieldOptionDropdown
							placeholder={__('Select', 'out-the-door-order-tasks-workflows-for-woocommerce')}
							options={fieldOptions?.priority || []}
							id="priority"
							name="field_priority"
							selected={priorityOption}
							onChange={handleFormChange}
						/>
					</div>
					<div>
						<label htmlFor="due-date">
							{__('Due Date', 'out-the-door-order-tasks-workflows-for-woocommerce')}
						</label>
						<DatePicker
							id="due-date"
							name="field_due_date"
							value={dueDate}
							onChange={handleFormChange}
						/>
					</div>
				</div>
				<div className="field-group">
					<div>
						<label htmlFor="title">
							{__('Title', 'out-the-door-order-tasks-workflows-for-woocommerce')}
						</label>
						<input
							id="title"
							name="title"
							type="text"
							defaultValue={
								task ? task.title : (initialValues?.title ?? '')
							}
							placeholder={__(
								'Write a name for your task.',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
							required
						/>
					</div>
				</div>
				<div className="field-group">
					<div>
						<label htmlFor="associated-orders">
							{__('Associated Orders', 'out-the-door-order-tasks-workflows-for-woocommerce')}
						</label>
						<AsyncMultiSelectField
							placeholder={__(
								'Search orders by number, customer, address…',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
							selectedOptions={associatedOrderIds}
							id="associated-orders"
							name="field_order"
							onSelect={handleFormChange}
							onRemove={handleFormChange}
							// ToDo: Lazy load for next pages
							onSearch={async (
								query: string,
								signal?: AbortSignal
							) => {
								const results = await searchOrders(
									query,
									signal
								);
								return results.map((order) => ({
									id: order.id,
									label: `#${order.id}`,
									searchLabel: `#${order.id} - ${order.billing?.first_name} ${order.billing?.last_name}`,
								}));
							}}
						/>
					</div>
				</div>
				<div className="field-group">
					<div>
						<label htmlFor="description">
							{__('Description', 'out-the-door-order-tasks-workflows-for-woocommerce')}
						</label>
						<textarea
							id="description"
							name="description"
							rows={6}
							placeholder={__(
								'Describe the task.',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
							defaultValue={
								task
									? task.description
									: (initialValues?.description ?? '')
							}
						/>
					</div>
				</div>
			</fieldset>
			<div className="action-group">
				<button
					type="button"
					className="wpo-button"
					onClick={onDone}
					disabled={isSubmitting}
				>
					{__('Cancel', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</button>
				<button
					type="submit"
					className="wpo-button wpo-button-primary"
					disabled={isSubmitting}
				>
					{isSubmitting && <span className="wpo-aom-spinner"></span>}
					{task
						? __('Update Task', 'out-the-door-order-tasks-workflows-for-woocommerce')
						: __('Create Task', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				</button>
			</div>
		</form>
	);
};
