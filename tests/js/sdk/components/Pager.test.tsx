import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Pager } from '@sdk/components/Pager';

/**
 * The pager as text, page buttons and ellipses in order, e.g. "1 … 4 5 6 … 10".
 *
 * @param container The rendered container.
 */
const tokensOf = (container: HTMLElement): string =>
	Array.from(
		container.querySelectorAll(
			'.wpo-otd-pager-page, .wpo-otd-pager-ellipsis'
		)
	)
		.map((node) => node.textContent)
		.join(' ');

describe('Pager', () => {
	it('renders nothing for a single page', () => {
		const { container } = render(
			<Pager currentPage={1} lastPage={1} onChange={jest.fn()} />
		);

		expect(container).toBeEmptyDOMElement();
	});

	it('shows the boundaries and a window round the current page', () => {
		const { container } = render(
			<Pager currentPage={5} lastPage={10} onChange={jest.fn()} />
		);

		expect(tokensOf(container)).toBe('1 … 4 5 6 … 10');
		expect(screen.getByRole('button', { name: '5' })).toHaveClass(
			'is-current'
		);
		expect(screen.getByRole('button', { name: '5' })).toBeDisabled();
	});

	it('spends the slot next to a boundary on a page, not an ellipsis', () => {
		const { container } = render(
			<Pager currentPage={1} lastPage={10} onChange={jest.fn()} />
		);

		expect(tokensOf(container)).toBe('1 2 3 4 5 … 10');
	});

	it('disables Previous on the first page and Next on the last', () => {
		const { rerender } = render(
			<Pager currentPage={1} lastPage={3} onChange={jest.fn()} />
		);

		expect(screen.getByRole('button', { name: 'Previous' })).toBeDisabled();
		expect(screen.getByRole('button', { name: 'Next' })).toBeEnabled();

		rerender(<Pager currentPage={3} lastPage={3} onChange={jest.fn()} />);

		expect(screen.getByRole('button', { name: 'Previous' })).toBeEnabled();
		expect(screen.getByRole('button', { name: 'Next' })).toBeDisabled();
	});

	it('asks for the page the user clicks', async () => {
		const user = userEvent.setup();
		const onChange = jest.fn();
		render(<Pager currentPage={5} lastPage={10} onChange={onChange} />);

		await user.click(screen.getByRole('button', { name: 'Next' }));
		await user.click(screen.getByRole('button', { name: 'Previous' }));
		await user.click(screen.getByRole('button', { name: '10' }));

		expect(onChange.mock.calls).toEqual([[6], [4], [10]]);
	});

	it('disables every button while loading', () => {
		render(
			<Pager
				currentPage={5}
				lastPage={10}
				onChange={jest.fn()}
				disabled
			/>
		);

		for (const button of screen.getAllByRole('button')) {
			expect(button).toBeDisabled();
		}
		expect(screen.getByRole('navigation')).toHaveClass('is-loading');
	});
});
