import React, { useLayoutEffect, useState } from 'react';
import { render, RenderOptions, RenderResult } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import {
	DialogProvider,
	StatusRoleProvider,
	TaskProvider,
	ToastProvider,
	useTasks,
} from '@sdk';
import type { FieldOption, Task, TaskField } from '@sdk';
import { LocalizedScreenData, setLocalized } from './localized';

export interface ProviderOptions {
	/** The router's starting location, e.g. '/task-manager/kanban'. */
	route?: string;
	/** Overrides for the order manager's localized data. */
	localized?: Partial<LocalizedScreenData>;
	/** Tasks the TaskProvider starts with. */
	tasks?: Task[];
	/** Field options the TaskProvider starts with, by field slug. */
	fieldOptions?: Record<string, FieldOption[]>;
	/** Task fields the TaskProvider starts with, by field slug. */
	taskFields?: Record<string, TaskField>;
}

export type RenderWithProvidersResult = RenderResult & {
	user: ReturnType<typeof userEvent.setup>;
};

/**
 * Puts the seed data into the TaskProvider before its children first render.
 *
 * @param props
 * @param props.seed
 * @param props.children
 */
function TaskSeed({
	seed,
	children,
}: {
	seed: Pick<ProviderOptions, 'tasks' | 'fieldOptions' | 'taskFields'>;
	children: React.ReactNode;
}) {
	const { setTasks, setFieldOptions, setTaskFields } = useTasks();
	const [seeded, setSeeded] = useState(false);

	useLayoutEffect(() => {
		if (seed.tasks) {
			setTasks(seed.tasks);
		}
		if (seed.fieldOptions) {
			setFieldOptions(seed.fieldOptions);
		}
		if (seed.taskFields) {
			setTaskFields(seed.taskFields);
		}
		setSeeded(true);
		// Seeds once, as a page load would.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	return seeded ? <>{children}</> : null;
}

/**
 * Render inside the providers the order manager page wraps its views in:
 * a router, then the status role, task, toast and dialog providers, in the
 * page's order. The order manager's localized data is set first, since the
 * providers read it.
 *
 * @param ui      The element to render.
 * @param options Provider options, plus RTL's own render options.
 * @return RTL's render result, plus a user-event instance.
 */
export function renderWithProviders(
	ui: React.ReactElement,
	options: ProviderOptions & Omit<RenderOptions, 'wrapper'> = {}
): RenderWithProvidersResult {
	const {
		route = '/',
		localized,
		tasks,
		fieldOptions,
		taskFields,
		...renderOptions
	} = options;

	setLocalized('WPO_OTD_OrderManager', localized);

	const Wrapper = ({ children }: { children: React.ReactNode }) => (
		<MemoryRouter initialEntries={[route]}>
			<StatusRoleProvider>
				<TaskProvider>
					<TaskSeed seed={{ tasks, fieldOptions, taskFields }}>
						<ToastProvider>
							<DialogProvider>{children}</DialogProvider>
						</ToastProvider>
					</TaskSeed>
				</TaskProvider>
			</StatusRoleProvider>
		</MemoryRouter>
	);

	const user = userEvent.setup();

	return { user, ...render(ui, { wrapper: Wrapper, ...renderOptions }) };
}
