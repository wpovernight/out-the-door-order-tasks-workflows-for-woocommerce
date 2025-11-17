import React from 'react';
import { useTasks } from '../../../context/TaskContext';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';
import { FieldOptionDropdown } from '@shared/components/FieldOptionDropdownField';
import { AsyncMultiSelectField } from '@shared/components/AsyncMultiSelectField';
import { fetchTaskFields, searchOrders } from '@shared/utils/api';

interface TaskFormProps {
	taskId?: number;
	columnId: number;
	onDone?: () => void;
}

export const TaskForm: React.FC<TaskFormProps> = ({
	taskId,
	columnId,
	onDone,
}) => {
	const {
		createTask,
		loadTaskFields,
		taskFields,
		fieldOptions,
		loadFieldOptions,
	} = useTasks();

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([
			loadTaskFields(),
			loadFieldOptions('status'),
			loadFieldOptions('priority'),
		]);
	}, [loadFieldOptions, loadTaskFields]);

	const submit = async (e: React.FormEvent) => {
		e.preventDefault();

		const form = e.target as HTMLFormElement;
		const formData = new FormData(form);

		const fieldValues: Record<string, any> = {};
		taskFields.forEach((field) => {
			const value = formData.get(`field_${field.slug}`);
			if (value !== null) {
				fieldValues[field.id] = value;
			}
		});

		const payload: Record<string, any> = {
			title: formData.get('title'),
			description: formData.get('description'),
			fields: fieldValues,
		};

		console.log(payload);

		await createTask(payload);
		// await loadTasks(true);
		onDone?.();
	};

	if (loadingStatus === 'loading') {
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

	return (
		<form onSubmit={submit} className="wpo-aom-task-form">
			<fieldset>
				<div className="wpo-aom-task-field-group">
					<div>
						<label htmlFor="status">Status</label>
						<FieldOptionDropdown
							placeholder="Select"
							options={fieldOptions.status}
							id="status"
							name="field_status"
							selected={fieldOptions?.status?.[columnId - 1]}
						/>
					</div>
					<div>
						<label htmlFor="priority">Priority</label>
						<FieldOptionDropdown
							placeholder="Select"
							options={fieldOptions?.priority || []}
							id="priority"
							name="field_priority"
							selected={fieldOptions?.priority?.[0]}
						/>
					</div>
					<div>
						<label htmlFor="due-date">Due Date</label>
						<input
							id="due-date"
							name="field_due_date"
							type="date"
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
							id="associated-orders"
							name="field_associated_orders"
							// ToDo: Lazy load for next pages
							onSearch={async (query: string) => {
								const results = await searchOrders(query);
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
						/>
					</div>
				</div>
			</fieldset>
			<div className="wpo-aom-actions">
				<button type="button" className="wpo-button" onClick={onDone}>
					Cancel
				</button>
				<button type="submit" className="wpo-button wpo-button-primary">
					{taskId ? 'Update Task' : 'Create Task'}
				</button>
			</div>
		</form>
	);
};
