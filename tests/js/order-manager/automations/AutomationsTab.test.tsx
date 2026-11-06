import React from 'react';
import { act, screen, within } from '@testing-library/react';
import { addFilter } from '@wordpress/hooks';
import { renderWithProviders } from '@otd-test-utils';
import Page from '@orderManager/components/Page';
import { CORE_TABS, TabProvider } from '@orderManager/context/TabContext';
import { AutomationsView } from '@orderManager/views/Automations/AutomationsView';
import { PRO_UPSELL_URL } from '@orderManager/views/Automations/constants';

const HINT = 'Available in Pro';

const renderPage = (route = '/automations') =>
	renderWithProviders(
		<TabProvider>
			<Page />
		</TabProvider>,
		{ route }
	);

/**
 * The row of the example rule with this title.
 *
 * @param title The rule's title.
 */
const rowOf = (title: string): HTMLElement => {
	const row = screen
		.getByRole('heading', { name: new RegExp(`^${title}`) })
		.closest('li');

	if (!row) {
		throw new Error(`No row for "${title}"`);
	}

	return row;
};

/**
 * The row's chips as "type:label", in order.
 *
 * @param row The rule's row.
 */
const chipsOf = (row: HTMLElement): string[] =>
	Array.from(row.querySelectorAll('.automations-chip')).map((chip) => {
		const type = Array.from(chip.classList)
			.find((name) => name.startsWith('automations-chip-'))
			?.replace('automations-chip-', '');
		const label = chip.querySelector('.automations-chip-label');

		return `${type}:${label?.textContent}`;
	});

/**
 * The page's inert controls: the search field, both add buttons and the
 * three toggles.
 */
const controls = (): HTMLElement[] => [
	screen.getByRole('searchbox', { name: 'Search' }),
	screen.getByRole('button', { name: /New rule/ }),
	...screen.getAllByRole('switch'),
	screen.getByRole('button', { name: 'Add new rule' }),
];

describe('Automations tab', () => {
	it('registers after Custom Order Status and opens at /automations', () => {
		expect(CORE_TABS.indexOf('automations')).toBe(
			CORE_TABS.indexOf('custom-order-status') + 1
		);

		renderPage();

		const tabs = within(
			document.getElementById('main-tabs') as HTMLElement
		).getAllByRole('button');
		expect(tabs.map((tab) => tab.textContent)).toEqual([
			'Dashboard',
			'Task Manager',
			'Custom Order Status',
			'Automations',
		]);
		expect(tabs[3].closest('li')).toHaveClass('active');
		expect(
			screen.getByRole('heading', { name: 'Active Rules' })
		).toBeInTheDocument();
	});

	it('also serves paths below /automations', () => {
		renderPage('/automations/rules/7');

		expect(
			screen.getByRole('heading', { name: 'Active Rules' })
		).toBeInTheDocument();
	});

	it('shows the three example rules with their chips', () => {
		renderPage();

		expect(screen.getAllByText('Example')).toHaveLength(3);

		expect(chipsOf(rowOf('Flag orders from one client'))).toEqual([
			'trigger:Order created',
			'condition:Company is "School A"',
			'action:Add note: School A order',
		]);
		expect(chipsOf(rowOf('Route embroidery to the right team'))).toEqual([
			'trigger:Order created',
			'condition:Any item in category Embroidery',
			'action:Create task: Embroidery',
			'more:+ 1 action',
		]);
		expect(
			chipsOf(rowOf('Keep order status in step with your board'))
		).toEqual([
			'trigger:Task moved to In progress',
			'action:Set order status: Processing',
		]);
	});

	it('draws an arrow between chips, not before the trigger or the count', () => {
		renderPage();

		const arrows = (title: string) =>
			Array.from(rowOf(title).querySelectorAll('.automations-chip')).map(
				(chip) => chip.querySelector('.automations-chip-arrow') !== null
			);

		expect(arrows('Route embroidery to the right team')).toEqual([
			false,
			true,
			true,
			false,
		]);
		expect(arrows('Keep order status in step with your board')).toEqual([
			false,
			true,
		]);
	});

	it('makes every control disabled, labelled and described by the hint', () => {
		renderPage();

		const all = controls();
		expect(all).toHaveLength(6);

		for (const control of all) {
			expect(control).toHaveAttribute('aria-disabled', 'true');
			expect(control).not.toBeDisabled();
			expect(control).toHaveAccessibleName();
			expect(control).toHaveAccessibleDescription(HINT);
		}

		const switches = screen.getAllByRole('switch');
		expect(
			switches.map((toggle) => toggle.getAttribute('aria-label'))
		).toEqual([
			'Switch on "Flag orders from one client"',
			'Switch on "Route embroidery to the right team"',
			'Switch on "Keep order status in step with your board"',
		]);
		for (const toggle of switches) {
			expect(toggle).not.toBeChecked();
		}
	});

	it('reaches every control by keyboard and shows its hint on focus', async () => {
		const { user } = renderPage();

		// The tab buttons come first.
		for (let i = 0; i < 4; i++) {
			await user.tab();
		}

		const order = [
			screen.getByRole('searchbox', { name: 'Search' }),
			screen.getByRole('button', { name: /New rule/ }),
			...screen.getAllByRole('switch'),
			screen.getByRole('button', { name: 'Add new rule' }),
		];

		for (const control of order) {
			await user.tab();
			expect(control).toHaveFocus();

			const hint = document.getElementById(
				control.getAttribute('aria-describedby') as string
			);
			expect(hint).toHaveClass('is-visible');
			expect(hint).toHaveTextContent(HINT);
		}

		await user.tab();
		expect(
			screen.getByRole('link', { name: /See what's in Pro/ })
		).toHaveFocus();

		// Moving on hides the last control's hint.
		const addRule = screen.getByRole('button', { name: 'Add new rule' });
		expect(
			document.getElementById(
				addRule.getAttribute('aria-describedby') as string
			)
		).not.toHaveClass('is-visible');
	});

	it('shows the hint on hover and hides it on leave and on Escape', async () => {
		const { user } = renderPage();

		const newRule = screen.getByRole('button', { name: /New rule/ });
		const hint = document.getElementById(
			newRule.getAttribute('aria-describedby') as string
		);
		expect(hint).not.toHaveClass('is-visible');

		await user.hover(newRule);
		expect(hint).toHaveClass('is-visible');

		await user.unhover(newRule);
		expect(hint).not.toHaveClass('is-visible');

		act(() => newRule.focus());
		expect(hint).toHaveClass('is-visible');
		await user.keyboard('{Escape}');
		expect(hint).not.toHaveClass('is-visible');
	});

	it('keeps the hint while its control is still hovered or focused', async () => {
		const { user } = renderPage();

		const newRule = screen.getByRole('button', { name: /New rule/ });
		const hint = document.getElementById(
			newRule.getAttribute('aria-describedby') as string
		);

		act(() => newRule.focus());
		await user.hover(newRule);
		await user.unhover(newRule);
		expect(hint).toHaveClass('is-visible');

		await user.hover(newRule);
		act(() => newRule.blur());
		expect(hint).toHaveClass('is-visible');

		await user.unhover(newRule);
		expect(hint).not.toHaveClass('is-visible');
	});

	it('shows the hint again after Escape when the control is hovered again', async () => {
		const { user } = renderPage();

		const newRule = screen.getByRole('button', { name: /New rule/ });
		const hint = document.getElementById(
			newRule.getAttribute('aria-describedby') as string
		);

		await user.hover(newRule);
		act(() => newRule.focus());
		await user.keyboard('{Escape}');
		expect(hint).not.toHaveClass('is-visible');

		await user.unhover(newRule);
		await user.hover(newRule);
		expect(hint).toHaveClass('is-visible');
	});

	it('changes nothing when the controls are used', async () => {
		const { user } = renderPage();

		const search = screen.getByRole('searchbox', { name: 'Search' });
		await user.type(search, 'School');
		expect(search).toHaveValue('');

		for (const toggle of screen.getAllByRole('switch')) {
			await user.click(toggle);
			expect(toggle).not.toBeChecked();
		}

		await user.click(screen.getByRole('button', { name: /New rule/ }));
		await user.click(screen.getByRole('button', { name: 'Add new rule' }));

		expect(screen.getAllByRole('switch')).toHaveLength(3);
		expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
		expect(
			screen.getByRole('heading', { name: 'Active Rules' })
		).toBeInTheDocument();
	});

	it('gives the rows nothing clickable but their toggle', () => {
		renderPage();

		const rows = document.querySelectorAll('.automations-rules > li');
		expect(rows).toHaveLength(3);

		for (const row of Array.from(rows)) {
			const clickable = row.querySelectorAll('a, button, [tabindex]');
			expect(
				Array.from(clickable).map((el) => el.getAttribute('role'))
			).toEqual(['switch']);
		}
	});

	it('links to Pro in a new tab from the Pro card', () => {
		renderPage();

		expect(
			screen.getByRole('heading', { name: /^Rules\s*Pro$/ })
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Build your own rules from your orders, products, customers and tasks. Set them up once and they run on their own.'
			)
		).toBeInTheDocument();

		const link = screen.getByRole('link', {
			name: "See what's in Pro - $99/year (opens in a new tab)",
		});
		expect(link).toHaveAttribute('href', PRO_UPSELL_URL);
		expect(link).toHaveAttribute('target', '_blank');
		expect(link).toHaveAttribute('rel', 'noopener noreferrer');
	});

	it('shows the footer line under the rules', () => {
		renderPage();

		expect(
			screen.getByText(
				'Rules do the repetitive part for you. Set one up once, and it happens every time — no one has to remember.'
			)
		).toBeInTheDocument();
	});

	it('hides the Pro card when wpo_otd.automations_show_upsell returns false', () => {
		addFilter('wpo_otd.automations_show_upsell', 'test', () => false);

		renderPage();

		expect(
			screen.getByRole('heading', { name: 'Active Rules' })
		).toBeInTheDocument();
		expect(screen.queryByRole('link')).not.toBeInTheDocument();
		expect(
			screen.queryByRole('heading', { name: /^Rules/ })
		).not.toBeInTheDocument();
	});

	it('renders the view wpo_otd.automations_view returns instead of the preview', () => {
		addFilter('wpo_otd.automations_view', 'test', () => (
			<p>Pro rules screen</p>
		));

		renderWithProviders(<AutomationsView />, { route: '/automations' });

		expect(screen.getByText('Pro rules screen')).toBeInTheDocument();
		expect(
			screen.queryByRole('heading', { name: 'Active Rules' })
		).not.toBeInTheDocument();
		expect(screen.queryByRole('link')).not.toBeInTheDocument();
		expect(
			screen.getByRole('heading', { name: 'Automations' })
		).toBeInTheDocument();
	});

	it('keeps the preview when wpo_otd.automations_view leaves it null', () => {
		const filter = jest.fn((view: React.ReactNode) => view);
		addFilter('wpo_otd.automations_view', 'test', filter);

		renderWithProviders(<AutomationsView />, { route: '/automations' });

		expect(filter).toHaveBeenCalledWith(null);
		expect(
			screen.getByRole('heading', { name: 'Active Rules' })
		).toBeInTheDocument();
	});
});
