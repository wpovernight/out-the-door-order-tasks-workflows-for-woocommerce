import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useTasks } from '@shared/context/TaskContext';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';
import { FieldOptionDropdown } from '@shared/components/FieldOptionDropdownField';
import { AsyncMultiSelectField } from '@shared/components/AsyncMultiSelectField';
import { TaskFormSkeleton } from '@shared/components/TaskFormSkeleton';
import { searchOrders } from '@shared/utils/api';
import { isFieldOption, Task } from '@shared/types/task';

interface TaskFormProps {
	task?: Task;
	columnId?: number;
	orderId?: number;
	onDone?: () => void;
	onTaskSaved?: (task: Task) => void;
}

export const TaskForm: React.FC<TaskFormProps> = ({
	task,
	columnId,
	orderId,
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

	const [isSubmitting, setIsSubmitting] = useState(false);
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

		try {
			const form = e.target as HTMLFormElement;
			const formData = new FormData(form);
			const payload = prepareFormData(formData);

			const savedTask = await saveTask(payload, task?.id);
			onTaskSaved?.(savedTask);
			onDone?.();
		} catch (error) {
			console.error('Failed to create task:', error);
		} finally {
			setIsSubmitting(false);
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
				{__('Error loading data. Please try again.', 'wpo-aom')}
			</div>
		);
	}

	const statusResolved = task
		? task.fields.find((field) => field.slug === 'status')?.values?.[0]
				?.resolved
		: null;
	const statusOption =
		statusResolved && isFieldOption(statusResolved)
			? statusResolved
			: fieldOptions?.status?.[(columnId ?? 1) - 1];

	const priorityResolved = task
		? task.fields.find((field) => field.slug === 'priority')?.values?.[0]
				?.resolved
		: null;
	const priorityOption =
		priorityResolved && isFieldOption(priorityResolved)
			? priorityResolved
			: fieldOptions?.priority?.[0];

	const dueDate = task
		? (task.fields.find((field) => field.slug === 'due_date')?.values?.[0]
				?.raw as string)
		: undefined;

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
			if (Number.isNaN(num)) {
				return null;
			}
			return { id: num, label: `#${raw}` };
		})
		.filter((item): item is { id: number; label: string } => item !== null);

	// If creating a new task and orderId is provided, pre-select it
	if (!task && orderId) {
		associatedOrderIds = [{ id: orderId, label: `#${orderId}` }];
	}

	// The form field name should follow the pattern: field_{field_slug}
	return (
		<form
			onSubmit={submit}
			className={`wpo-aom-task-form ${isSubmitting ? 'submitting' : ''}`}
		>
			<fieldset disabled={isSubmitting}>
				<div className="field-group">
					<div>
						<label htmlFor="status">
							{__('Status', 'wpo-aom')}
						</label>
						<FieldOptionDropdown
							placeholder={__('Select', 'wpo-aom')}
							options={fieldOptions.status}
							id="status"
							name="field_status"
							selected={statusOption}
						/>
					</div>
					<div>
						<label htmlFor="priority">
							{__('Priority', 'wpo-aom')}
						</label>
						<FieldOptionDropdown
							placeholder={__('Select', 'wpo-aom')}
							options={fieldOptions?.priority || []}
							id="priority"
							name="field_priority"
							selected={priorityOption}
						/>
					</div>
					<div>
						<label htmlFor="due-date">
							{__('Due Date', 'wpo-aom')}
						</label>
						<input
							id="due-date"
							name="field_due_date"
							type="date"
							defaultValue={dueDate}
						/>
					</div>
				</div>
				<div className="field-group">
					<div>
						<label htmlFor="title">{__('Title', 'wpo-aom')}</label>
						<input
							id="title"
							name="title"
							type="text"
							defaultValue={task ? task.title : ''}
							placeholder={__(
								'Write a name for your task.',
								'wpo-aom'
							)}
							required
						/>
					</div>
				</div>
				{/*For now, we will hide the associated orders field as it has been decided to automatically link order*/}
				{/*to the task when created from order edit page.*/}
				{/*<div className="field-group">*/}
				{/*	<div>*/}
				{/*		<label htmlFor="associated-orders">*/}
				{/*			{__('Associated Orders', 'wpo-aom')}*/}
				{/*		</label>*/}
				{/*		<AsyncMultiSelectField*/}
				{/*			placeholder={__(*/}
				{/*				'Search orders by number, customer, address…',*/}
				{/*				'wpo-aom'*/}
				{/*			)}*/}
				{/*			selectedOptions={associatedOrderIds}*/}
				{/*			id="associated-orders"*/}
				{/*			name="field_order"*/}
				{/*			// ToDo: Lazy load for next pages*/}
				{/*			onSearch={async (*/}
				{/*				query: string,*/}
				{/*				signal?: AbortSignal*/}
				{/*			) => {*/}
				{/*				const results = await searchOrders(*/}
				{/*					query,*/}
				{/*					signal*/}
				{/*				);*/}
				{/*				return results.map((order) => ({*/}
				{/*					id: order.id,*/}
				{/*					label: `#${order.id}`,*/}
				{/*					searchLabel: `#${order.id} - ${order.billing?.first_name} ${order.billing?.last_name}`,*/}
				{/*				}));*/}
				{/*			}}*/}
				{/*		/>*/}
				{/*	</div>*/}
				{/*</div>*/}

				{/* Add associated order IDs as a hidden field to be processed on submit */}
				{associatedOrderIds && associatedOrderIds.length > 0 && (
					<input
						type="hidden"
						name="field_order"
						value={associatedOrderIds
							.map((order) => order.id)
							.join(',')}
					/>
				)}
				<div className="field-group">
					<div>
						<label htmlFor="description">
							{__('Description', 'wpo-aom')}
						</label>
						<textarea
							id="description"
							name="description"
							rows={4}
							placeholder={__('Describe the task.', 'wpo-aom')}
							defaultValue={task ? task.description : ''}
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
					{__('Cancel', 'wpo-aom')}
				</button>
				<button
					type="submit"
					className="wpo-button wpo-button-primary"
					disabled={isSubmitting}
				>
					{isSubmitting && <span className="wpo-aom-spinner"></span>}
					{task
						? __('Update Task', 'wpo-aom')
						: __('Create Task', 'wpo-aom')}
				</button>
			</div>
		</form>
	);
};
