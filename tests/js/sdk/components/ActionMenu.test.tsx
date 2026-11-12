import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ActionMenu, type ActionMenuItem } from '@sdk/components/ActionMenu';

const itemsWith = (
	onSelect: (key: string) => void = jest.fn()
): ActionMenuItem[] => [
	{
		key: 'compare',
		label: 'Compare a value',
		description: 'Test one value.',
		onSelect: () => onSelect('compare'),
	},
	{
		key: 'duplicate',
		label: 'Duplicate',
		onSelect: () => onSelect('duplicate'),
	},
	{
		key: 'delete',
		label: 'Delete',
		danger: true,
		onSelect: () => onSelect('delete'),
	},
];

describe('ActionMenu', () => {
	it('opens from a three-dot button named by its label, focusing the first choice', async () => {
		const user = userEvent.setup();
		render(<ActionMenu label="Group 1 options" items={itemsWith()} />);

		const button = screen.getByRole('button', { name: 'Group 1 options' });
		expect(button).toHaveClass('wpo-otd-action-menu-dots');
		expect(button).toHaveAttribute('aria-expanded', 'false');
		expect(screen.queryByRole('menu')).not.toBeInTheDocument();

		await user.click(button);

		const menu = screen.getByRole('menu', { name: 'Group 1 options' });
		expect(button).toHaveAttribute('aria-expanded', 'true');
		expect(button).toHaveAttribute('aria-controls', menu.id);
		expect(menu.parentElement).toBe(document.body);
		expect(screen.getAllByRole('menuitem')[0]).toHaveFocus();
	});

	it('shows descriptions, a heading and a note, and marks a destructive choice', async () => {
		const user = userEvent.setup();
		render(
			<ActionMenu
				label="Add condition"
				buttonContent="Add condition"
				buttonClassName="add-link"
				heading="Add condition"
				note="Conditions in a group all have to be true."
				items={itemsWith()}
			/>
		);

		await user.click(screen.getByRole('button', { name: 'Add condition' }));

		expect(
			screen.getByRole('menuitem', {
				name: 'Compare a value Test one value.',
			})
		).toBeInTheDocument();
		expect(screen.getByRole('menuitem', { name: 'Delete' })).toHaveClass(
			'is-danger'
		);
		expect(screen.getByRole('menu')).toHaveTextContent(
			/^Add condition.*Conditions in a group all have to be true\.$/
		);
	});

	it('runs a choice, closes and gives focus back to the button', async () => {
		const user = userEvent.setup();
		const onSelect = jest.fn();
		render(<ActionMenu label="Options" items={itemsWith(onSelect)} />);

		await user.click(screen.getByRole('button', { name: 'Options' }));
		await user.click(screen.getByRole('menuitem', { name: 'Duplicate' }));

		expect(onSelect).toHaveBeenCalledWith('duplicate');
		expect(onSelect).toHaveBeenCalledTimes(1);
		expect(screen.queryByRole('menu')).not.toBeInTheDocument();
		expect(screen.getByRole('button', { name: 'Options' })).toHaveFocus();
	});

	it('moves with the arrow keys, Home and End, wrapping round', async () => {
		const user = userEvent.setup();
		render(<ActionMenu label="Options" items={itemsWith()} />);

		await user.click(screen.getByRole('button', { name: 'Options' }));
		const [first, second, last] = screen.getAllByRole('menuitem');

		await user.keyboard('{ArrowDown}');
		expect(second).toHaveFocus();
		await user.keyboard('{End}');
		expect(last).toHaveFocus();
		await user.keyboard('{ArrowDown}');
		expect(first).toHaveFocus();
		await user.keyboard('{ArrowUp}');
		expect(last).toHaveFocus();
		await user.keyboard('{Home}');
		expect(first).toHaveFocus();
	});

	it('closes on Escape, focus back on the button, without choosing', async () => {
		const user = userEvent.setup();
		const onSelect = jest.fn();
		render(<ActionMenu label="Options" items={itemsWith(onSelect)} />);

		await user.click(screen.getByRole('button', { name: 'Options' }));
		await user.keyboard('{Escape}');

		expect(screen.queryByRole('menu')).not.toBeInTheDocument();
		expect(screen.getByRole('button', { name: 'Options' })).toHaveFocus();
		expect(onSelect).not.toHaveBeenCalled();
	});

	it('closes on a click outside', async () => {
		const user = userEvent.setup();
		render(
			<>
				<p>Elsewhere</p>
				<ActionMenu label="Options" items={itemsWith()} />
			</>
		);

		await user.click(screen.getByRole('button', { name: 'Options' }));
		await user.click(screen.getByText('Elsewhere'));

		expect(screen.queryByRole('menu')).not.toBeInTheDocument();
	});

	it('cannot be opened while disabled', async () => {
		const user = userEvent.setup();
		render(<ActionMenu label="Options" items={itemsWith()} disabled />);

		await user.click(screen.getByRole('button', { name: 'Options' }));

		expect(screen.getByRole('button', { name: 'Options' })).toBeDisabled();
		expect(screen.queryByRole('menu')).not.toBeInTheDocument();
	});
});
