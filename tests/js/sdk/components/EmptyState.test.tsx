import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { EmptyState } from '@sdk/components/LoadingSkeleton';

describe('EmptyState', () => {
	it('shows only its message and the default button without a title or variant', () => {
		const { container } = render(
			<EmptyState
				message="Nothing here."
				actionText="Add one"
				onAction={jest.fn()}
			/>
		);

		expect(
			container.querySelector('.wpo-otd-empty-state-icon--note')
		).not.toBeNull();
		expect(
			container.querySelector('.wpo-otd-empty-state-title')
		).toBeNull();
		expect(screen.getByText('Nothing here.')).toHaveClass(
			'wpo-otd-empty-state-message'
		);
		expect(screen.getByText('Nothing here.')).not.toHaveClass(
			'wpo-otd-empty-state-description'
		);
		expect(screen.getByRole('button', { name: 'Add one' })).toHaveAttribute(
			'class',
			'wpo-otd-empty-state-action'
		);
	});

	it('shows a title with the message as its description', () => {
		render(
			<EmptyState title="No rules yet." message="Rules do the work." />
		);

		const title = screen.getByText('No rules yet.');
		const description = screen.getByText('Rules do the work.');

		expect(title).toHaveClass('wpo-otd-empty-state-title');
		expect(description).toHaveClass(
			'wpo-otd-empty-state-message',
			'wpo-otd-empty-state-description'
		);
		expect(title.nextElementSibling).toBe(description);
	});

	it('draws the rules illustration', () => {
		const { container } = render(
			<EmptyState icon="rules" message="No rules yet." />
		);

		const icon = container.querySelector('.wpo-otd-empty-state-icon');

		expect(icon).toHaveClass('wpo-otd-empty-state-icon--rules');
		expect(icon).toBeEmptyDOMElement();
	});

	it.each(['primary', 'secondary'] as const)(
		'shows the action as a %s button and calls it',
		async (variant) => {
			const onAction = jest.fn();

			render(
				<EmptyState
					message="Nothing here."
					actionText="Go"
					onAction={onAction}
					actionVariant={variant}
					actionButtonProps={{ className: 'extra' }}
				/>
			);

			const button = screen.getByRole('button', { name: 'Go' });

			expect(button).toHaveAttribute(
				'class',
				`wpo-button wpo-button-${variant} extra`
			);

			await userEvent.click(button);

			expect(onAction).toHaveBeenCalledTimes(1);
		}
	);

	it('shows a second action after the first as a secondary button, the two side by side', async () => {
		const onAction = jest.fn();
		const onSecondaryAction = jest.fn();

		const { container } = render(
			<EmptyState
				message="No runs match."
				actionText="Clear search"
				onAction={onAction}
				actionVariant="secondary"
				secondaryActionText="Search all runs"
				onSecondaryAction={onSecondaryAction}
			/>
		);

		const actions = container.querySelector(
			'.wpo-otd-empty-state > .wpo-otd-empty-state-actions'
		);

		expect(
			Array.from(actions!.querySelectorAll('button')).map(
				(button) => button.textContent
			)
		).toEqual(['Clear search', 'Search all runs']);
		expect(
			screen.getByRole('button', { name: 'Search all runs' })
		).toHaveAttribute('class', 'wpo-button wpo-button-secondary');

		await userEvent.click(
			screen.getByRole('button', { name: 'Search all runs' })
		);

		expect(onSecondaryAction).toHaveBeenCalledTimes(1);
		expect(onAction).not.toHaveBeenCalled();
	});

	it('keeps a single action a direct child, with no wrapper', () => {
		const { container } = render(
			<EmptyState
				message="Nothing."
				actionText="Go"
				onAction={jest.fn()}
			/>
		);

		expect(
			container.querySelector('.wpo-otd-empty-state-actions')
		).toBeNull();
		expect(
			container.querySelector('.wpo-otd-empty-state > button')
		).not.toBeNull();
	});
});
