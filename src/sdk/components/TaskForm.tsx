import React, {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useTasks } from '@sdk/context/TaskContext';
import { useAsyncLoader } from '@sdk/hooks/useAsyncLoader';
import { FieldOptionDropdown } from '@sdk/components/FieldOptionDropdownField';
import { AsyncMultiSelectField } from '@sdk/components/AsyncMultiSelectField';
import { TaskFormSkeleton } from '@sdk/components/TaskFormSkeleton';
import { searchOrders } from '@sdk/utils/api';
import { FieldOption, isFieldOption, Task } from '@sdk/types/task';
import { useSidebarModal } from '@sdk/context/SidebarModalContext';
import { useChoice, useConfirm } from '@sdk/context/DialogContext';
import { ToastType, useToast } from '@sdk/context/ToastContext';
import { DatePicker } from '@sdk/components/DatePicker';
import { useLeaveGuard } from '@sdk/hooks/useLeaveGuard';

export interface TaskFormInitialValues {
	title?: string;
	description?: string;
	dueDate?: string;
	statusIndex?: number;
	priorityIndex?: number;
	orderIds?: number[];
}

/**
 * A task as it is set up to be created later, by something other than the
 * form: no task is saved, only these values handed back.
 */
export interface TaskSettings {
	title: string;
	description?: string;
	/** A status option's id. */
	status?: number;
	/** A priority option's id. */
	priority?: number;
	/** Days after the day the task is created that it is due, 0 to 36,500. */
	dueInDays?: number;
}

/**
 * The form in settings mode: it edits a task's settings and saves no task.
 */
export interface TaskSettingsMode {
	value: TaskSettings;
	/** Called with the values on Save, before the form closes. */
	onSave: (value: TaskSettings) => void;
	/** Shown under the description, e.g. the placeholders its text may hold. */
	placeholders?: React.ReactNode;
	/** Every field disabled, and only Close. */
	readOnly?: boolean;
}

interface TaskFormProps {
	task?: Task;
	initialValues?: TaskFormInitialValues;
	onDone?: () => void;
	onTaskSaved?: (task: Task) => void;
	/** Set up a task without saving one, instead of adding or editing a task. */
	settings?: TaskSettingsMode;
}

/** The most days ahead a task set up in settings mode may be due. */
export const MAX_DUE_IN_DAYS = 36500;

/**
 * The option with this id; one no longer offered shows as "{id} (deleted)".
 *
 * @param options The options.
 * @param id      The chosen id; none for no choice.
 */
const optionById = (
	options: FieldOption[],
	id: number | undefined
): FieldOption | undefined => {
	if (undefined === id) {
		return undefined;
	}

	return (
		options.find((option) => option.id === id) ?? {
			id,
			field_id: 0,
			slug: '',
			label: sprintf(
				/* translators: %d: the id of an option that no longer exists. */
				__(
					'%d (deleted)',
					'out-the-door-order-tasks-workflows-for-woocommerce'
				),
				id
			),
			color: '',
			position: 0,
		}
	);
};

/**
 * A whole number typed into a form, or none.
 *
 * @param value The form's value.
 */
const wholeNumber = (value: FormDataEntryValue | null): number | undefined => {
	if ('string' !== typeof value || '' === value.trim()) {
		return undefined;
	}
	const number = Number(value);
	return Number.isInteger(number) ? number : undefined;
};

/**
 * What the form asks before edits are lost: Discard, or Keep editing.
 */
const discardPrompt = () => ({
	title: __(
		'Save your changes?',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	),
	message: __(
		'You have unsaved work. Discarding will permanently erase your recent edits.',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	),
	confirmText: __(
		'Discard',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	),
	cancelText: __(
		'Keep editing',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	),
	action: 'save' as const,
	invertActions: true,
});

/**
 * The settings a form in settings mode holds, what is empty left out.
 *
 * @param form The form.
 */
const settingsIn = (form: HTMLFormElement): TaskSettings => {
	const formData = new FormData(form);
	const text = (name: string): string => {
		const entry = formData.get(name);
		return 'string' === typeof entry ? entry : '';
	};
	const value: TaskSettings = { title: text('title') };
	const description = text('description');
	if ('' !== description) {
		value.description = description;
	}
	const status = wholeNumber(formData.get('field_status'));
	if (undefined !== status) {
		value.status = status;
	}
	const priority = wholeNumber(formData.get('field_priority'));
	if (undefined !== priority) {
		value.priority = priority;
	}
	const dueInDays = wholeNumber(formData.get('due_in_days'));
	if (undefined !== dueInDays) {
		value.dueInDays = dueInDays;
	}
	return value;
};

export const TaskForm: React.FC<TaskFormProps> = ({
	task,
	initialValues,
	onDone,
	onTaskSaved,
	settings,
}) => {
	const isSettings = undefined !== settings;
	const readOnly = isSettings && true === settings.readOnly;
	const {
		saveTask,
		loadTaskFields,
		taskFields,
		fieldOptions,
		loadFieldOptions,
	} = useTasks();
	const { setBeforeClose } = useSidebarModal();
	const confirm = useConfirm();
	const choose = useChoice();
	const { addToast } = useToast();
	// The form, for the close prompt's Save in settings mode to read.
	const formRef = useRef<HTMLFormElement>(null);

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

		const prompt = discardPrompt();

		if (!settings || settings.readOnly) {
			return await confirm(prompt);
		}

		// Settings mode saves nothing but the values, so the prompt can
		// offer to save them too: checked as Save checks them, then closed.
		const answer = await choose({
			...prompt,
			alternativeText: __(
				'Save',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
		});
		if ('alternative' !== answer) {
			return 'confirm' === answer;
		}
		const form = formRef.current;
		if (!form || !form.reportValidity()) {
			return false;
		}
		settings.onSave(settingsIn(form));
		isDirtyRef.current = false;
		return true;
	}, [confirm, choose, settings]);

	useEffect(() => {
		setBeforeClose(beforeCloseGuard);
		return () => setBeforeClose(null);
	}, [setBeforeClose, beforeCloseGuard]);

	// The options settings mode starts with, kept from render to render: the
	// dropdowns take a new one as a new choice, which would undo a change.
	const settingsStatus = useMemo(
		() => optionById(fieldOptions.status ?? [], settings?.value.status),
		[fieldOptions.status, settings]
	);
	const settingsPriority = useMemo(
		() => optionById(fieldOptions.priority ?? [], settings?.value.priority),
		[fieldOptions.priority, settings]
	);

	// Settings mode's edits live only in the panel until its Save, so the
	// screen is guarded against being left or reloaded while it has some.
	const [guardsLeave, setGuardsLeave] = useState(false);
	useLeaveGuard(guardsLeave, () => confirm(discardPrompt()));

	const handleFormChange = () => {
		isDirtyRef.current = true;
		if (isSettings && !readOnly) {
			setGuardsLeave(true);
		}
	};
	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await Promise.all([
			// Settings mode saves no task, so it needs none of its fields.
			...(isSettings ? [] : [loadTaskFields()]),
			loadFieldOptions('status'),
			loadFieldOptions('priority'),
		]);
	}, [loadFieldOptions, loadTaskFields, isSettings]);

	const saveSettings = (form: HTMLFormElement) => {
		if (!settings || readOnly) {
			return;
		}
		settings.onSave(settingsIn(form));
		isDirtyRef.current = false;
		onDone?.();
	};

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

			let value: any;
			if (arrayInputs.includes(fieldSlug)) {
				// Multiple value fields are always sent, even when empty, so that
				// removing every selection clears the stored values.
				value = formData
					.getAll(`${formFieldName}[]`)
					.filter((entry) => entry !== '');
			} else {
				const rawValue = formData.getAll(formFieldName);
				if (rawValue.length === 0) {
					continue; // Skip if no value provided
				}
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
		if (isSettings) {
			saveSettings(e.target as HTMLFormElement);
			return;
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
					? __(
							'Failed to update task.',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)
					: __(
							'Failed to create task.',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						),
				message:
					error instanceof Error && error.message
						? error.message
						: __(
								'Please try again.',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							),
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
	// Settings mode may leave the status and priority unset, so a field with
	// no options left is ready once loaded; adding a task needs one of each.
	const isDataReady = isSettings
		? Array.isArray(fieldOptions.status) &&
			Array.isArray(fieldOptions.priority)
		: Object.keys(taskFields).length > 0 &&
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
	let statusOption: FieldOption | undefined =
		statusResolved && isFieldOption(statusResolved)
			? statusResolved
			: fieldOptions?.status?.[defaultStatusIndex];

	const priorityResolved = task
		? task.fields.find((field) => field.slug === 'priority')?.values?.[0]
				?.resolved
		: null;
	const defaultPriorityIndex = initialValues?.priorityIndex ?? 0;
	let priorityOption: FieldOption | undefined =
		priorityResolved && isFieldOption(priorityResolved)
			? priorityResolved
			: fieldOptions?.priority?.[defaultPriorityIndex];

	let title = task ? task.title : (initialValues?.title ?? '');
	let description = task
		? task.description
		: (initialValues?.description ?? '');
	let submitText = task
		? __(
				'Update Task',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			)
		: __(
				'Create Task',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			);

	const selectText = __(
		'Select',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	);
	let statusEmpty: string | undefined;
	let priorityEmpty: string | undefined;

	// Settings mode shows the values it was given, an unset status or
	// priority as a choice of its own that a set one can go back to, and
	// saves them rather than a task.
	if (settings) {
		statusEmpty = __(
			'First status column',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		);
		priorityEmpty = __(
			'No priority',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		);
		statusOption = settingsStatus;
		priorityOption = settingsPriority;
		title = settings.value.title;
		description = settings.value.description ?? '';
		submitText = __(
			'Save',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		);
	}

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
			ref={formRef}
			onSubmit={submit}
			onChange={handleFormChange}
			className={`wpo-otd-task-form ${isSubmitting ? 'submitting' : ''}`}
		>
			<fieldset disabled={isSubmitting || readOnly}>
				<div className="field-group">
					<div>
						<label htmlFor="status">
							{__(
								'Status',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
						</label>
						<FieldOptionDropdown
							placeholder={statusEmpty ?? selectText}
							options={fieldOptions.status}
							id="status"
							name="field_status"
							selected={statusOption}
							onChange={handleFormChange}
							emptyLabel={statusEmpty}
							onClear={handleFormChange}
						/>
					</div>
					<div>
						<label htmlFor="priority">
							{__(
								'Priority',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
						</label>
						<FieldOptionDropdown
							placeholder={priorityEmpty ?? selectText}
							options={fieldOptions?.priority || []}
							id="priority"
							name="field_priority"
							selected={priorityOption}
							onChange={handleFormChange}
							emptyLabel={priorityEmpty}
							onClear={handleFormChange}
						/>
					</div>
					<div>
						{settings ? (
							<>
								<label htmlFor="due-in-days">
									{__(
										'Due in',
										'out-the-door-order-tasks-workflows-for-woocommerce'
									)}
								</label>
								<span className="wpo-otd-task-form-due-in">
									<input
										id="due-in-days"
										name="due_in_days"
										type="number"
										className="wpo-otd-input"
										min={0}
										max={MAX_DUE_IN_DAYS}
										step={1}
										defaultValue={
											settings.value.dueInDays ?? ''
										}
										aria-describedby="due-in-days-unit"
									/>
									<span id="due-in-days-unit">
										{__(
											'days',
											'out-the-door-order-tasks-workflows-for-woocommerce'
										)}
									</span>
								</span>
							</>
						) : (
							<>
								<label htmlFor="due-date">
									{__(
										'Due Date',
										'out-the-door-order-tasks-workflows-for-woocommerce'
									)}
								</label>
								<DatePicker
									id="due-date"
									name="field_due_date"
									value={dueDate}
									onChange={handleFormChange}
								/>
							</>
						)}
					</div>
				</div>
				<div className="field-group">
					<div>
						<label htmlFor="title">
							{__(
								'Title',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
						</label>
						<input
							id="title"
							name="title"
							type="text"
							className="wpo-otd-input"
							defaultValue={title}
							maxLength={settings ? 255 : undefined}
							placeholder={__(
								'Write a name for your task.',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
							required
						/>
					</div>
				</div>
				{!settings && (
					<div className="field-group">
						<div>
							<label htmlFor="associated-orders">
								{__(
									'Associated Orders',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
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
									return results.map((order) => {
										const customerName =
											[
												order.billing?.first_name,
												order.billing?.last_name,
											]
												.filter(Boolean)
												.join(' ')
												.trim() ||
											order.billing?.company?.trim();

										return {
											id: order.id,
											label: `#${order.id}`,
											searchLabel: customerName
												? `#${order.id} - ${customerName}`
												: `#${order.id}`,
										};
									});
								}}
							/>
						</div>
					</div>
				)}
				<div className="field-group">
					<div>
						<label htmlFor="description">
							{__(
								'Description',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
						</label>
						<textarea
							id="description"
							className="wpo-otd-input"
							name="description"
							rows={6}
							placeholder={__(
								'Describe the task.',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
							defaultValue={description}
						/>
					</div>
				</div>
				{settings?.placeholders && (
					<div className="field-group">
						<div>{settings.placeholders}</div>
					</div>
				)}
			</fieldset>
			{readOnly ? (
				<div className="action-group">
					<button
						type="button"
						className="wpo-button"
						onClick={onDone}
					>
						{__(
							'Close',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)}
					</button>
				</div>
			) : (
				<div className="action-group">
					<button
						type="button"
						className="wpo-button"
						onClick={onDone}
						disabled={isSubmitting}
					>
						{__(
							'Cancel',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)}
					</button>
					<button
						type="submit"
						className="wpo-button wpo-button-primary"
						disabled={isSubmitting}
					>
						{isSubmitting && (
							<span className="wpo-otd-spinner"></span>
						)}
						{submitText}
					</button>
				</div>
			)}
		</form>
	);
};
