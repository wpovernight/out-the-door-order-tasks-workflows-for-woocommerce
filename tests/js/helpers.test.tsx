import React from 'react';
import { screen, waitFor } from '@testing-library/react';
import { addFilter, applyFilters, didFilter } from '@wordpress/hooks';
import { useLocation } from 'react-router-dom';
import { useTasks } from '@sdk';
import type { FieldOption } from '@sdk';
import {
	clearLocalized,
	envelope,
	installWpGlobals,
	mockFetch,
	ok,
	renderWithProviders,
	resetHooks,
	setLocalized,
	takeUnmockedRequests,
	TEST_NONCE,
} from '@otd-test-utils';

const option = (slug: string, position: number): FieldOption => ({
	id: position,
	field_id: 1,
	slug,
	label: slug.toUpperCase(),
	color: '#ffffff',
	position,
});

function PriorityOptions({ load = false }: { load?: boolean }) {
	const { fieldOptions, loadFieldOptions } = useTasks();
	const location = useLocation();

	React.useEffect(() => {
		if (load) {
			loadFieldOptions('priority');
		}
	}, [load, loadFieldOptions]);

	return (
		<div>
			<p>path: {location.pathname}</p>
			<ul>
				{(fieldOptions.priority ?? []).map((entry) => (
					<li key={entry.slug}>{entry.label}</li>
				))}
			</ul>
		</div>
	);
}

describe('renderWithProviders', () => {
	it('renders inside the task provider with seeded data and a route', () => {
		renderWithProviders(<PriorityOptions />, {
			route: '/task-manager/kanban',
			fieldOptions: { priority: [option('low', 1), option('high', 2)] },
		});

		expect(
			screen.getByText('path: /task-manager/kanban')
		).toBeInTheDocument();
		expect(
			screen.getAllByRole('listitem').map((item) => item.textContent)
		).toEqual(['LOW', 'HIGH']);
	});

	it('lets providers load through a mocked REST API with the nonce', async () => {
		const api = mockFetch({
			'GET /tasks/fields/priority/options': ok(
				envelope([option('urgent', 1)])
			),
		});

		renderWithProviders(<PriorityOptions load />);

		expect(await screen.findByText('URGENT')).toBeInTheDocument();
		expect(api.calls).toHaveLength(1);
		expect(api.calls[0].headers['X-WP-Nonce']).toBe(TEST_NONCE);
	});
});

describe('the fetch guard', () => {
	it('records a request nothing answered', async () => {
		await expect(fetch('http://localhost/wp-json/x')).rejects.toThrow(
			'Unmocked fetch: GET http://localhost/wp-json/x'
		);

		// Taken here, so this test passes; left alone, the setup fails it.
		expect(takeUnmockedRequests()).toEqual([
			'GET http://localhost/wp-json/x',
		]);
	});

	it('records a request the route table has no answer for', async () => {
		mockFetch({ 'GET /tasks': ok(envelope([])) });

		await expect(
			fetch('http://localhost/wp-json/wc/v3/wpo/otd/tasks', {
				method: 'POST',
			})
		).rejects.toThrow('Unmocked fetch');

		await waitFor(() =>
			expect(takeUnmockedRequests()).toEqual([
				'POST http://localhost/wp-json/wc/v3/wpo/otd/tasks',
			])
		);
	});
});

describe('resetHooks', () => {
	it('removes filters and their run counts', () => {
		addFilter('otd_test.value', 'otd-test', (value: number) => value + 1);
		expect(applyFilters('otd_test.value', 1)).toBe(2);

		resetHooks();

		expect(applyFilters('otd_test.value', 1)).toBe(1);
		expect(didFilter('otd_test.value')).toBe(1);
	});
});

describe('setLocalized', () => {
	it('fills defaults, takes overrides and is cleared', () => {
		const data = setLocalized('WPO_OTD_OrderManager', { nonce: 'abc' });

		expect(data.nonce).toBe('abc');
		expect(data.apiNamespace).toBe('wc/v3/wpo/otd');
		expect(window.WPO_OTD_OrderManager).toBe(data);

		clearLocalized();

		expect(window.WPO_OTD_OrderManager).toBeUndefined();
	});
});

describe('installWpGlobals', () => {
	it('publishes the imported modules as the page globals, and removes them', () => {
		const uninstall = installWpGlobals();
		const win = window as unknown as {
			wp?: { hooks: { applyFilters: unknown } };
			wpo?: { otd: { sdk: { useTasks: unknown } } };
		};

		expect(win.wp?.hooks.applyFilters).toBe(applyFilters);
		expect(win.wpo?.otd.sdk.useTasks).toBe(useTasks);

		uninstall();

		expect(win.wp).toBeUndefined();
		expect(win.wpo).toBeUndefined();
	});
});
