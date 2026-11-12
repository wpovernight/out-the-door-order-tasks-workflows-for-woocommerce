import React from 'react';
import { screen, waitFor, within } from '@testing-library/react';
import { addFilter } from '@wordpress/hooks';
import { useLocation } from 'react-router-dom';
import { renderWithProviders } from '@otd-test-utils';
import { useLeaveGuard } from '@sdk';
import type { LeaveCheck } from '@sdk';
import Header from '@orderManager/components/Header';
import { TabProvider } from '@orderManager/context/TabContext';

const Where = () => (
	<output data-testid="path">{useLocation().pathname}</output>
);

const Guard = ({ ask }: { ask: LeaveCheck }) => {
	useLeaveGuard(true, ask);
	return null;
};

const renderHeader = (guard?: LeaveCheck) =>
	renderWithProviders(
		<TabProvider>
			<Header />
			<Where />
			{guard && <Guard ask={guard} />}
		</TabProvider>,
		{ route: '/automations/rules/new' }
	);

const tabButton = (name: RegExp | string) =>
	within(document.getElementById('main-tabs') as HTMLElement).getByRole(
		'button',
		{ name }
	);

describe('Order Manager header', () => {
	it('renders a tab label wpo_otd.tab_labels gives as a React node', () => {
		addFilter(
			'wpo_otd.tab_labels',
			'test',
			(labels: Record<string, React.ReactNode>) => ({
				...labels,
				automations: (
					<>
						{labels.automations}
						<span className="wpo-count-badge">3</span>
					</>
				),
			})
		);

		renderHeader();

		const tab = tabButton(/^Automations/);
		expect(tab).toHaveTextContent('Automations3');
		expect(within(tab).getByText('3')).toHaveClass('wpo-count-badge');
		expect(tabButton('Dashboard')).toBeInTheDocument();
	});

	it('falls back to the slug for a tab with no label', () => {
		addFilter(
			'wpo_otd.tab_labels',
			'test',
			(labels: Record<string, React.ReactNode>) => {
				const { automations, ...rest } = labels;
				return rest;
			}
		);

		renderHeader();

		expect(tabButton('automations')).toBeInTheDocument();
		expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
			'Out the Door'
		);
	});

	it('switches tabs at once when no view guards against leaving', async () => {
		const { user } = renderHeader();

		await user.click(tabButton('Dashboard'));

		expect(screen.getByTestId('path')).toHaveTextContent('/dashboard');
	});

	it('stays when a view guarding against leaving refuses', async () => {
		const ask = jest.fn(() => Promise.resolve(false));
		const { user } = renderHeader(ask);

		await user.click(tabButton('Dashboard'));

		await waitFor(() => expect(ask).toHaveBeenCalledTimes(1));
		expect(screen.getByTestId('path')).toHaveTextContent(
			'/automations/rules/new'
		);
	});

	it('switches once a view guarding against leaving lets it go', async () => {
		const ask = jest.fn(() => Promise.resolve(true));
		const { user } = renderHeader(ask);

		await user.click(tabButton(/^Automations/));

		await waitFor(() =>
			expect(screen.getByTestId('path')).toHaveTextContent(
				/^\/automations$/
			)
		);
		expect(ask).toHaveBeenCalledTimes(1);
	});
});
