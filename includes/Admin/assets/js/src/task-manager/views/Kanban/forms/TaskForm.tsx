import React from 'react';
import { useTasks } from '../../../context/TaskContext';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';
import { FieldOptionDropdown } from '@shared/components/FieldOptionDropdownField';

type Status = 'idle' | 'loading' | 'error';

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
	const { saveTask, loadTasks } = useTasks();
	const { fieldOptions, loadFieldOptions, fieldOptionsLoaded } = useTasks();

	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([
			loadFieldOptions('status'),
			loadFieldOptions('priority'),
		]);
	}, [loadFieldOptions]);

	const submit = async (e: React.FormEvent) => {
		e.preventDefault();
		// await saveTask();
		// await loadTasks(true);
		onDone?.();
	};

	if (loadingStatus === 'loading') {
		return (
			<div className="loading-spinner">
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
							selected={fieldOptions?.status?.[0]}
						/>
					</div>
					<div>
						<label htmlFor="priority">Priority</label>
						<FieldOptionDropdown
							placeholder="Select"
							options={fieldOptions?.priority || []}
							id="priority"
							selected={fieldOptions?.priority?.[0]}
						/>
					</div>
					<div>
						<label htmlFor="due-date">Due Date</label>
						<input id="due-date" name="due_date" type="date" />
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
						<select
							id="associated-orders"
							name="associated_orders"
							className="wpo-aom-multiselect  wc-enhanced-select"
							data-placeholder="Search orders by number"
							multiple
						></select>
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
