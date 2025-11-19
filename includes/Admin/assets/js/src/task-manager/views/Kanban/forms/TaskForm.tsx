import React, { useState } from 'react';
import { useTasks } from '../../../context/TaskContext';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';
import { FieldOptionDropdown } from '@shared/components/FieldOptionDropdownField';
import { AsyncMultiSelectField } from '@shared/components/AsyncMultiSelectField';
import { searchOrders } from '@shared/utils/api';
import { isFieldOption, Task } from '@shared/types/task';

interface TaskFormProps {
	task?: Task;
	columnId?: number;
	onDone?: () => void;
	onTaskSaved?: (task: Task) => void;
}

export const TaskForm: React.FC<TaskFormProps> = ({
	task,
	columnId,
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

	const submit = async (e: React.FormEvent) => {
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
		return (
			<div className="loading-spinner" style={{ padding: '0 1em' }}>
				{(window as any).WPO_AOM_TaskManager.loading}
			</div>
		);
	}

	if (loadingStatus === 'error') {
		return (
			<div className="error-message">
				{(window as any).WPO_AOM_TaskManager.errorLoading}
			</div>
		);
	}

	let statusOption = task
		? task.fields.find((field) => field.slug === 'status')?.values?.[0]
				?.resolved
		: null;
	statusOption =
		statusOption && isFieldOption(statusOption)
			? statusOption
			: fieldOptions?.status?.[(columnId ?? 1) - 1];

	let priorityOption = task
		? task.fields.find((field) => field.slug === 'priority')?.values?.[0]
				?.resolved
		: null;
	priorityOption =
		priorityOption && isFieldOption(priorityOption)
			? priorityOption
			: fieldOptions?.priority?.[0];

	const dueDate = task
		? (task.fields.find((field) => field.slug === 'due-date')?.values?.[0]
				?.raw as string)
		: undefined;

	const associatedOrders = task?.fields.find(
		(field) => field.slug === 'order'
	)?.values;
	const associatedOrderIds = associatedOrders
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

	// The form field name should follow the pattern: field_{field_slug}
	return (
		<form
			onSubmit={submit}
			className={`wpo-aom-task-form ${isSubmitting ? 'submitting' : ''}`}
		>
			<fieldset disabled={isSubmitting}>
				<div className="wpo-aom-task-field-group">
					<div>
						<label htmlFor="status">Status</label>
						<FieldOptionDropdown
							placeholder="Select"
							options={fieldOptions.status}
							id="status"
							name="field_status"
							selected={statusOption}
						/>
					</div>
					<div>
						<label htmlFor="priority">Priority</label>
						<FieldOptionDropdown
							placeholder="Select"
							options={fieldOptions?.priority || []}
							id="priority"
							name="field_priority"
							selected={priorityOption}
						/>
					</div>
					<div>
						<label htmlFor="due-date">Due Date</label>
						<input
							id="due-date"
							name="field_due_date"
							type="date"
							defaultValue={dueDate}
						/>
					</div>
				</div>
				<div className="wpo-aom-task-field-group">
					<div>
						<label htmlFor="title">Title</label>
						<input
							id="title"
							name="title"
							type="text"
							defaultValue={task ? task.title : ''}
							placeholder="Write a name for your task."
							required
						/>
					</div>
				</div>
				<div className="wpo-aom-task-field-group">
					<div>
						<label htmlFor="associated-orders">
							Associated Orders
						</label>
						<AsyncMultiSelectField
							placeholder="Search orders by number, customer, address..."
							selectedOptions={associatedOrderIds}
							id="associated-orders"
							name="field_order"
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
							onSelect={(option) => {
								console.log('Selected order:', option);
							}}
							onRemove={(optionId) => {
								console.log('Removed order ID:', optionId);
							}}
						/>
					</div>
				</div>
				<div className="wpo-aom-task-field-group">
					<div>
						<label htmlFor="description">Description</label>
						<textarea
							id="description"
							name="description"
							rows={4}
							placeholder="Describe the task."
							defaultValue={task ? task.description : ''}
						/>
					</div>
				</div>
			</fieldset>
			<div className="wpo-aom-actions">
				<button
					type="button"
					className="wpo-button"
					onClick={onDone}
					disabled={isSubmitting}
				>
					Cancel
				</button>
				<button
					type="submit"
					className="wpo-button wpo-button-primary"
					disabled={isSubmitting}
				>
					{isSubmitting && <span className="wpo-aom-spinner"></span>}
					{task ? 'Update Task' : 'Create Task'}
				</button>
			</div>
		</form>
	);
};
