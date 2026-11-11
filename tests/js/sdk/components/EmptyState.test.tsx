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
});
