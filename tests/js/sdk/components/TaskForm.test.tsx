import React from 'react';
import { screen, waitFor, within } from '@testing-library/react';
import { envelope, mockFetch, ok, renderWithProviders } from '@otd-test-utils';
import {
	confirmLeave,
	hasLeaveGuard,
	SidebarModalProvider,
	TaskForm,
	useTaskCreation,
} from '@sdk';
import type { FieldOption, TaskSettings } from '@sdk';

const option = (
	fieldId: number,
	slug: string,
	id = fieldId * 10
): FieldOption => ({
	id,
	field_id: fieldId,
	slug,
	label: slug,
	color: '#ffffff',
	position: 1,
});

/**
 * The task form, once its fields and options have loaded.
 */
const renderForm = async () => {
	const mock = mockFetch({
		'GET /tasks/fields': ok(
			envelope({
				status: {
					id: 1,
					label: 'Status',
					type: 'select',
					slug: 'status',
					is_required: true,
					is_editable: true,
					is_protected: true,
				},
			})
		),
		'GET /tasks/fields/status/options': ok(envelope([option(1, 'todo')])),
		'GET /tasks/fields/priority/options': ok(
			envelope([option(2, 'normal')])
		),
		'POST /tasks': ok(envelope({ id: 1, title: 'Pack', fields: [] })),
	});

	const view = renderWithProviders(
		<SidebarModalProvider>
			<TaskForm />
		</SidebarModalProvider>
	);

	await screen.findByLabelText('Title');

	return { ...view, mock };
};

/**
 * Opens the settings panel with a button, as a caller of useTaskCreation does.
 *
 * @param props
 * @param props.value
 * @param props.onSave
 * @param props.readOnly
 */
const OpenSettings = ({
	value,
	onSave,
	readOnly,
}: {
	value: TaskSettings;
	onSave: (value: TaskSettings) => void;
	readOnly?: boolean;
}) => {
	const { openTaskSettings } = useTaskCreation();

	return (
		<button
			type="button"
			onClick={() =>
				openTaskSettings({
					title: 'Set up task',
					value,
					onSave,
					readOnly,
					placeholders: <p>{'{order_number}'}</p>,
				})
			}
		>
			Open
		</button>
	);
};

/**
 * The form in settings mode, open in its panel, without the task fields
 * loaded: a request for them fails the test.
 *
 * @param value      The settings it starts with.
 * @param readOnly   Whether nothing can be changed.
 * @param priorities The priority options it loads.
 */
const renderSettings = async (
	value: TaskSettings,
	readOnly?: boolean,
	priorities: FieldOption[] = [option(2, 'Low', 21), option(2, 'High', 22)]
) => {
	const mock = mockFetch({
		'GET /tasks/fields/status/options': ok(
			envelope([option(1, 'To do', 11), option(1, 'Done', 12)])
		),
		'GET /tasks/fields/priority/options': ok(envelope(priorities)),
		'POST /tasks': ok(envelope({ id: 1, title: 'Pack', fields: [] })),
	});
	const onSave = jest.fn();
	const view = renderWithProviders(
		<SidebarModalProvider>
			<OpenSettings value={value} onSave={onSave} readOnly={readOnly} />
		</SidebarModalProvider>
	);

	await view.user.click(screen.getByRole('button', { name: 'Open' }));
	await screen.findByLabelText('Title');

	return { ...view, mock, onSave };
};

/**
 * Opens Add Task in its panel, as the Task Manager does.
 */
const OpenAdd = () => {
	const { openCreateTaskModal } = useTaskCreation();

	return (
		<button
			type="button"
			onClick={() => openCreateTaskModal({ title: 'Add Task' })}
		>
			Open
		</button>
	);
};

/**
 * The close prompt's buttons, in order.
 */
const promptButtons = async () => {
	await screen.findByText('Save your changes?');
	const dialog = document.querySelector('dialog') as HTMLElement;
	return within(dialog)
		.getAllByRole('button')
		.map((button) => button.textContent);
};

const panelClosedIn = () =>
	waitFor(() =>
		expect(screen.queryByLabelText('Title')).not.toBeInTheDocument()
	);

/**
 * Whether a reload or closing the page is held back to ask first.
 */
const unloadPrevented = (): boolean => {
	const event = new Event('beforeunload', { cancelable: true });
	const preventDefault = jest.spyOn(event, 'preventDefault');
	window.dispatchEvent(event);
	return preventDefault.mock.calls.length > 0;
};

const dialogProto = HTMLDialogElement.prototype as Partial<HTMLDialogElement>;

/**
 * jsdom has no modal dialogs: these stand in for them.
 */
const installDialogStubs = () => {
	dialogProto.showModal = function (this: HTMLDialogElement) {
		this.setAttribute('open', '');
	};
	dialogProto.close = function (this: HTMLDialogElement) {
		this.removeAttribute('open');
	};
};

const removeDialogStubs = () => {
	delete dialogProto.showModal;
	delete dialogProto.close;
};

const posts = (calls: { method: string; path: string }[]) =>
	calls.filter((call) => 'POST' === call.method);

describe('TaskForm', () => {
	it("draws its own title and description fields with core's shared field look", async () => {
		await renderForm();

		expect(screen.getByLabelText('Title')).toHaveClass('wpo-otd-input');
		expect(screen.getByLabelText('Description')).toHaveClass(
			'wpo-otd-input'
		);
	});

	it("leaves the order search field, another component's, to the form's scoped styles", async () => {
		await renderForm();

		expect(screen.getByLabelText('Associated Orders')).not.toHaveClass(
			'wpo-otd-input'
		);
	});

	it('adds a task as before: the orders and the due date shown, a task posted', async () => {
		const { user, mock } = await renderForm();

		// A status is always chosen: no choice of none is offered.
		await user.click(screen.getByLabelText('Status'));
		expect(
			screen.getAllByRole('button').map((button) => button.textContent)
		).not.toContain('First status column');
		await user.click(screen.getByLabelText('Priority'));
		expect(
			screen.getAllByRole('button').map((button) => button.textContent)
		).not.toContain('No priority');

		expect(screen.getByLabelText('Associated Orders')).toBeInTheDocument();
		expect(screen.getByLabelText('Due Date')).toBeInTheDocument();
		expect(screen.queryByLabelText('Due in')).not.toBeInTheDocument();
		expect(screen.getByLabelText('Title')).not.toHaveAttribute('maxLength');

		await user.type(screen.getByLabelText('Title'), 'Pack');
		await user.click(screen.getByRole('button', { name: 'Create Task' }));

		await waitFor(() => expect(posts(mock.calls)).toHaveLength(1));
		expect(mock.calls[mock.calls.length - 1].path).toBe('/tasks');
	});
});

describe('TaskForm in settings mode', () => {
	// jsdom has no modal dialogs: these stand in for them, for the dialog
	// asking before edits are discarded.
	const proto = HTMLDialogElement.prototype as Partial<HTMLDialogElement>;
	beforeEach(() => {
		proto.showModal = function (this: HTMLDialogElement) {
			this.setAttribute('open', '');
		};
		proto.close = function (this: HTMLDialogElement) {
			this.removeAttribute('open');
		};
	});
	afterEach(() => {
		delete proto.showModal;
		delete proto.close;
	});

	it('shows the values it is given, with no orders and days in place of a date', async () => {
		await renderSettings({
			title: 'Pack {order_number}',
			description: 'Wrap it',
			status: 12,
			priority: 22,
			dueInDays: 2,
		});

		expect(screen.getByLabelText('Title')).toHaveValue(
			'Pack {order_number}'
		);
		expect(screen.getByLabelText('Title')).toHaveAttribute(
			'maxLength',
			'255'
		);
		expect(screen.getByLabelText('Description')).toHaveValue('Wrap it');
		expect(screen.getByLabelText('Status')).toHaveTextContent('Done');
		expect(screen.getByLabelText('Priority')).toHaveTextContent('High');
		expect(screen.getByLabelText('Due in')).toHaveValue(2);
		expect(screen.getByLabelText('Due in')).toHaveAttribute('min', '0');
		expect(screen.getByLabelText('Due in')).toHaveAttribute('max', '36500');
		expect(screen.getByText('{order_number}')).toBeInTheDocument();
		expect(
			screen.queryByLabelText('Associated Orders')
		).not.toBeInTheDocument();
		expect(screen.queryByLabelText('Due Date')).not.toBeInTheDocument();
	});

	it('shows a status or priority no longer offered as deleted, and keeps it', async () => {
		const { user, onSave } = await renderSettings({
			title: 'Pack',
			status: 99,
			priority: 98,
		});

		expect(screen.getByLabelText('Status')).toHaveTextContent(
			'99 (deleted)'
		);
		expect(screen.getByLabelText('Priority')).toHaveTextContent(
			'98 (deleted)'
		);

		await user.click(screen.getByRole('button', { name: 'Save' }));

		expect(onSave).toHaveBeenCalledWith({
			title: 'Pack',
			status: 99,
			priority: 98,
		});
	});

	it('hands the values to onSave and closes, saving no task', async () => {
		const { user, mock, onSave } = await renderSettings({ title: '' });

		expect(screen.getByLabelText('Status')).toHaveTextContent(
			'First status column'
		);
		expect(screen.getByLabelText('Priority')).toHaveTextContent(
			'No priority'
		);
		await user.type(
			screen.getByLabelText('Title'),
			// A doubled brace types one.
			'Pack {{order_number}'
		);
		await user.type(screen.getByLabelText('Description'), 'Wrap it');
		await user.click(screen.getByLabelText('Status'));
		await user.click(screen.getByRole('button', { name: 'Done' }));
		await user.click(screen.getByLabelText('Priority'));
		await user.click(screen.getByRole('button', { name: 'High' }));
		await user.type(screen.getByLabelText('Due in'), '3');
		await user.click(screen.getByRole('button', { name: 'Save' }));

		expect(onSave).toHaveBeenCalledTimes(1);
		expect(onSave).toHaveBeenCalledWith({
			title: 'Pack {order_number}',
			description: 'Wrap it',
			status: 12,
			priority: 22,
			dueInDays: 3,
		});
		await waitFor(() =>
			expect(screen.queryByLabelText('Title')).not.toBeInTheDocument()
		);
		expect(posts(mock.calls)).toHaveLength(0);
		expect(mock.calls.some((call) => '/tasks/fields' === call.path)).toBe(
			false
		);
		expect(screen.queryByRole('status')).not.toBeInTheDocument();
	});

	it('leaves out what is empty: no description, status, priority or due day', async () => {
		const { user, onSave } = await renderSettings({ title: 'Pack' });

		await user.click(screen.getByRole('button', { name: 'Save' }));

		expect(onSave).toHaveBeenCalledWith({ title: 'Pack' });
	});

	it('closes on Cancel without calling onSave', async () => {
		const { user, onSave } = await renderSettings({ title: 'Pack' });

		await user.click(screen.getByRole('button', { name: 'Cancel' }));

		await waitFor(() =>
			expect(screen.queryByLabelText('Title')).not.toBeInTheDocument()
		);
		expect(onSave).not.toHaveBeenCalled();
	});

	it('asks before discarding edits on Cancel, and Discard saves nothing', async () => {
		const { user, onSave } = await renderSettings({ title: 'Pack' });

		await user.type(screen.getByLabelText('Title'), ' it');
		await user.click(screen.getByRole('button', { name: 'Cancel' }));
		await screen.findByText('Save your changes?');
		await user.click(screen.getByRole('button', { name: 'Discard' }));

		await waitFor(() =>
			expect(screen.queryByLabelText('Title')).not.toBeInTheDocument()
		);
		expect(onSave).not.toHaveBeenCalled();
	});

	it('read-only, disables every field and offers only Close', async () => {
		const { user, onSave } = await renderSettings(
			{ title: 'Pack', status: 11, dueInDays: 0 },
			true
		);

		for (const label of [
			'Title',
			'Description',
			'Status',
			'Priority',
			'Due in',
		]) {
			expect(screen.getByLabelText(label)).toBeDisabled();
		}
		expect(screen.getByLabelText('Due in')).toHaveValue(0);
		expect(
			screen.queryByRole('button', { name: 'Save' })
		).not.toBeInTheDocument();
		expect(
			screen.queryByRole('button', { name: 'Cancel' })
		).not.toBeInTheDocument();

		// The panel's header has a Close of its own; this is the form's.
		const form = screen.getByLabelText('Title').closest('form')!;
		await user.click(within(form).getByRole('button', { name: 'Close' }));

		await waitFor(() =>
			expect(screen.queryByLabelText('Title')).not.toBeInTheDocument()
		);
		expect(onSave).not.toHaveBeenCalled();
	});

	it('clears a status and a priority chosen, leaving them out of the values', async () => {
		const { user, onSave } = await renderSettings({
			title: 'Pack',
			status: 12,
			priority: 22,
		});

		await user.click(screen.getByLabelText('Status'));
		await user.click(
			screen.getByRole('button', { name: 'First status column' })
		);
		await user.click(screen.getByLabelText('Priority'));
		await user.click(screen.getByRole('button', { name: 'No priority' }));
		expect(screen.getByLabelText('Status')).toHaveTextContent(
			'First status column'
		);
		await user.click(screen.getByRole('button', { name: 'Save' }));

		expect(onSave).toHaveBeenCalledWith({ title: 'Pack' });
	});

	it('opens with no priority options left, a saved one shown as deleted', async () => {
		const { user, onSave } = await renderSettings(
			{ title: 'Pack', priority: 22 },
			false,
			[]
		);

		expect(screen.getByLabelText('Priority')).toHaveTextContent(
			'22 (deleted)'
		);
		await user.click(screen.getByLabelText('Priority'));
		await user.click(screen.getByRole('button', { name: 'No priority' }));
		await user.click(screen.getByRole('button', { name: 'Save' }));

		expect(onSave).toHaveBeenCalledWith({ title: 'Pack' });
	});

	it('opens read-only with no priority options left', async () => {
		await renderSettings({ title: 'Pack' }, true, []);

		expect(screen.getByLabelText('Priority')).toBeDisabled();
		expect(screen.getByLabelText('Priority')).toHaveTextContent(
			'No priority'
		);
	});

	it('offers Save when closing after edits, which hands the values over and closes', async () => {
		const { user, onSave } = await renderSettings({ title: 'Pack' });

		await user.type(screen.getByLabelText('Title'), ' it');
		await user.click(screen.getByRole('button', { name: 'Cancel' }));
		expect(await promptButtons()).toEqual([
			'Save',
			'Discard',
			'Keep editing',
		]);
		const dialog = document.querySelector('dialog') as HTMLElement;
		await user.click(within(dialog).getByRole('button', { name: 'Save' }));

		await panelClosedIn();
		expect(onSave).toHaveBeenCalledTimes(1);
		expect(onSave).toHaveBeenCalledWith({ title: 'Pack it' });
	});

	it("keeps the panel open when the prompt's Save finds the title empty", async () => {
		const { user, onSave } = await renderSettings({ title: 'Pack' });

		await user.clear(screen.getByLabelText('Title'));
		await user.click(screen.getByRole('button', { name: 'Cancel' }));
		await promptButtons();
		const dialog = document.querySelector('dialog') as HTMLElement;
		await user.click(within(dialog).getByRole('button', { name: 'Save' }));

		await waitFor(() =>
			expect(
				screen.queryByText('Save your changes?')
			).not.toBeInTheDocument()
		);
		expect(screen.getByLabelText('Title')).toBeInTheDocument();
		expect(onSave).not.toHaveBeenCalled();
	});

	it('keeps editing when the prompt is told to', async () => {
		const { user, onSave } = await renderSettings({ title: 'Pack' });

		await user.type(screen.getByLabelText('Title'), ' it');
		await user.click(screen.getByRole('button', { name: 'Cancel' }));
		await promptButtons();
		await user.click(screen.getByRole('button', { name: 'Keep editing' }));

		expect(screen.getByLabelText('Title')).toHaveValue('Pack it');
		expect(onSave).not.toHaveBeenCalled();
	});
});

describe('leaving the screen with the settings panel open', () => {
	beforeEach(installDialogStubs);
	afterEach(removeDialogStubs);

	it('is free before any edit', async () => {
		await renderSettings({ title: 'Pack' });

		expect(hasLeaveGuard()).toBe(false);
		expect(unloadPrevented()).toBe(false);
	});

	it('asks before edits not yet saved are lost, and stays on Keep editing', async () => {
		const { user, onSave } = await renderSettings({ title: 'Pack' });

		await user.type(screen.getByLabelText('Title'), ' it');
		expect(hasLeaveGuard()).toBe(true);
		expect(unloadPrevented()).toBe(true);

		const leaving = confirmLeave();
		await screen.findByText('Save your changes?');
		await user.click(screen.getByRole('button', { name: 'Keep editing' }));
		await expect(leaving).resolves.toBe(false);
		expect(screen.getByLabelText('Title')).toHaveValue('Pack it');

		const leavingAgain = confirmLeave();
		await screen.findByText('Save your changes?');
		await user.click(screen.getByRole('button', { name: 'Discard' }));
		await expect(leavingAgain).resolves.toBe(true);
		expect(onSave).not.toHaveBeenCalled();
	});

	it('is free again once the panel saved and closed', async () => {
		const { user } = await renderSettings({ title: 'Pack' });

		await user.type(screen.getByLabelText('Title'), ' it');
		await user.click(screen.getByRole('button', { name: 'Save' }));
		await panelClosedIn();

		expect(hasLeaveGuard()).toBe(false);
		expect(unloadPrevented()).toBe(false);
	});

	it('is free read-only', async () => {
		await renderSettings({ title: 'Pack' }, true);

		expect(hasLeaveGuard()).toBe(false);
	});
});

describe("Add Task's close prompt", () => {
	const proto = HTMLDialogElement.prototype as Partial<HTMLDialogElement>;
	beforeEach(() => {
		proto.showModal = function (this: HTMLDialogElement) {
			this.setAttribute('open', '');
		};
		proto.close = function (this: HTMLDialogElement) {
			this.removeAttribute('open');
		};
	});
	afterEach(() => {
		delete proto.showModal;
		delete proto.close;
	});

	it('offers only Discard and Keep editing, as before', async () => {
		mockFetch({
			'GET /tasks/fields': ok(
				envelope({
					status: {
						id: 1,
						label: 'Status',
						type: 'select',
						slug: 'status',
						is_required: true,
						is_editable: true,
						is_protected: true,
					},
				})
			),
			'GET /tasks/fields/status/options': ok(
				envelope([option(1, 'todo')])
			),
			'GET /tasks/fields/priority/options': ok(
				envelope([option(2, 'normal')])
			),
		});
		const { user } = renderWithProviders(
			<SidebarModalProvider>
				<OpenAdd />
			</SidebarModalProvider>
		);
		await user.click(screen.getByRole('button', { name: 'Open' }));
		await user.type(await screen.findByLabelText('Title'), 'Pack');
		await user.click(screen.getByRole('button', { name: 'Cancel' }));

		// Adding a task guards against being left as before: not at all.
		expect(hasLeaveGuard()).toBe(false);
		expect(await promptButtons()).toEqual(['Discard', 'Keep editing']);
		expect(document.querySelector('.dialog-actions')).not.toHaveClass(
			'dialog-actions-stacked'
		);
		expect(
			document.querySelector('.dialog-actions .wpo-button-primary')
		).toHaveTextContent('Keep editing');
		await user.click(screen.getByRole('button', { name: 'Keep editing' }));
	});
});
