import React from 'react';
import { screen, within } from '@testing-library/react';
import { addFilter } from '@wordpress/hooks';
import { renderWithProviders } from '@otd-test-utils';
import Header from '@orderManager/components/Header';
import { TabProvider } from '@orderManager/context/TabContext';

const renderHeader = () =>
	renderWithProviders(
		<TabProvider>
			<Header />
		</TabProvider>,
		{ route: '/automations' }
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
});
