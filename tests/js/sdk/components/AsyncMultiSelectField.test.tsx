import React, { useState } from 'react';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
	AsyncMultiSelectField,
	type AsyncMultiSelectOption,
} from '@sdk/components/AsyncMultiSelectField';

const COUNTRIES: AsyncMultiSelectOption[] = [
	{ id: 'AT', label: 'Austria' },
	{ id: 'DE', label: 'Germany' },
	{ id: 'RO', label: 'Romania' },
];

const chips = (container: HTMLElement): string[] =>
	Array.from(
		container.querySelectorAll(
			'.wpo-otd-async-multi-select-selected-options > li'
		)
	).map((chip) => chip.textContent?.replace('Close', '').trim() ?? '');

/**
 * A field whose choice its parent keeps, as a controlled field is used.
 *
 * @param props
 * @param props.start    The options chosen to start with.
 * @param props.onChosen Called with each choice.
 * @param props.rest     Any other props.
 */
const Controlled = ({
	start = [],
	onChosen = jest.fn(),
	...rest
}: Partial<React.ComponentProps<typeof AsyncMultiSelectField>> & {
	start?: AsyncMultiSelectOption[];
	onChosen?: (options: AsyncMultiSelectOption[]) => void;
}) => {
	const [chosen, setChosen] = useState(start);

	return (
		<AsyncMultiSelectField
			placeholder="Search countries"
			ariaLabel="Countries"
			selectedOptions={chosen}
			onChange={(next) => {
				setChosen(next);
				onChosen(next);
			}}
			{...rest}
		/>
	);
};

describe('AsyncMultiSelectField', () => {
	describe('as the task form uses it', () => {
		it('searches what is typed, shows search labels, keeps its own choice and submits it as hidden inputs', async () => {
			const user = userEvent.setup();
			const onSelect = jest.fn();
			const onSearch = jest.fn(async (query: string) =>
				[
					{ id: 12, label: '#12', searchLabel: '#12 - Sam' },
					{ id: 40, label: '#40', searchLabel: '#40 - Kim' },
				].filter((order) => order.searchLabel.includes(query))
			);
			const { container } = render(
				<AsyncMultiSelectField
					placeholder="Search orders"
					selectedOptions={[{ id: 7, label: '#7' }]}
					name="field_order"
					onSearch={onSearch}
					onSelect={onSelect}
				/>
			);

			await user.type(screen.getByPlaceholderText('Search orders'), '#');
			await user.click(
				await screen.findByRole('button', { name: '#40 - Kim' })
			);

			expect(onSearch).toHaveBeenCalledWith(
				'#',
				expect.any(AbortSignal),
				1
			);
			expect(onSelect).toHaveBeenCalledWith(
				expect.objectContaining({ id: 40 })
			);
			expect(chips(container)).toEqual(['#7', '#40']);
			expect(
				Array.from(
					container.querySelectorAll('input[type="hidden"]')
				).map((input) => (input as HTMLInputElement).value)
			).toEqual(['7', '40']);
		});

		it('lists nothing before anything is typed', async () => {
			const user = userEvent.setup();
			const onSearch = jest.fn(async () => COUNTRIES);
			render(
				<AsyncMultiSelectField
					placeholder="Search orders"
					onSearch={onSearch}
				/>
			);

			await user.click(screen.getByPlaceholderText('Search orders'));
			await new Promise((resolve) => setTimeout(resolve, 350));

			expect(onSearch).not.toHaveBeenCalled();
			expect(
				document.querySelector('.wpo-otd-async-multi-select-options')
			).toBeNull();
		});
	});

	it('follows the options its parent chose, with string ids, and removes one', async () => {
		const user = userEvent.setup();
		const onChosen = jest.fn();
		const { container } = render(
			<Controlled start={COUNTRIES.slice(0, 2)} onChosen={onChosen} />
		);

		expect(chips(container)).toEqual(['Austria', 'Germany']);

		const austria = container.querySelector(
			'.wpo-otd-async-multi-select-selected-options > li'
		) as HTMLElement;
		await user.click(within(austria).getByRole('button'));

		expect(onChosen).toHaveBeenLastCalledWith([COUNTRIES[1]]);
		expect(chips(container)).toEqual(['Germany']);
	});

	it('lists options on focus in browse mode, leaving out those chosen', async () => {
		const user = userEvent.setup();
		const onSearch = jest.fn(async () => COUNTRIES);
		render(
			<Controlled start={[COUNTRIES[0]]} onSearch={onSearch} browse />
		);

		await user.click(screen.getByRole('textbox', { name: 'Countries' }));

		expect(
			await screen.findByRole('button', { name: 'Germany' })
		).toBeInTheDocument();
		expect(onSearch).toHaveBeenCalledWith('', expect.any(AbortSignal), 1);
		expect(
			screen.queryByRole('button', { name: 'Austria' })
		).not.toBeInTheDocument();
	});

	it('loads the next page and adds it to the list', async () => {
		const user = userEvent.setup();
		const onSearch = jest.fn(
			async (_query: string, _signal: AbortSignal, page: number) =>
				1 === page
					? { options: COUNTRIES.slice(0, 2), hasMore: true }
					: { options: COUNTRIES.slice(2), hasMore: false }
		);
		render(<Controlled onSearch={onSearch} browse />);

		await user.click(screen.getByRole('textbox', { name: 'Countries' }));
		await user.click(
			await screen.findByRole('button', { name: 'Load more' })
		);

		expect(
			await screen.findByRole('button', { name: 'Romania' })
		).toBeInTheDocument();
		expect(
			screen.getByRole('button', { name: 'Austria' })
		).toBeInTheDocument();
		expect(onSearch).toHaveBeenLastCalledWith(
			'',
			expect.any(AbortSignal),
			2
		);
		expect(
			screen.queryByRole('button', { name: 'Load more' })
		).not.toBeInTheDocument();
	});

	it('adds typed text with Enter or its "Add" entry, when it may be added', async () => {
		const user = userEvent.setup();
		const onChosen = jest.fn();
		const { container } = render(
			<Controlled creatable onChosen={onChosen} />
		);
		const input = screen.getByRole('textbox', { name: 'Countries' });

		await user.type(input, ' SW1A 1AA {Enter}');
		expect(onChosen).toHaveBeenLastCalledWith([
			{ id: 'SW1A 1AA', label: 'SW1A 1AA' },
		]);

		await user.type(input, 'EC1');
		await user.click(screen.getByRole('button', { name: 'Add “EC1”' }));
		expect(chips(container)).toEqual(['SW1A 1AA', 'EC1']);
	});

	it('says why typed text cannot be added, and does not add it', async () => {
		const user = userEvent.setup();
		const onChosen = jest.fn();
		render(
			<Controlled
				creatable
				onChosen={onChosen}
				validate={(text) =>
					/^\d+$/.test(text) ? null : 'Type a whole number.'
				}
			/>
		);

		await user.type(
			screen.getByRole('textbox', { name: 'Countries' }),
			'1.5{Enter}'
		);

		expect(screen.getByText('Type a whole number.')).toBeInTheDocument();
		expect(
			screen.queryByRole('button', { name: /^Add/ })
		).not.toBeInTheDocument();
		expect(onChosen).not.toHaveBeenCalled();
	});

	it('drops the search field once as many as allowed are chosen', async () => {
		const user = userEvent.setup();
		render(<Controlled creatable max={1} />);

		await user.type(
			screen.getByRole('textbox', { name: 'Countries' }),
			'Gift{Enter}'
		);

		expect(
			screen.queryByRole('textbox', { name: 'Countries' })
		).not.toBeInTheDocument();
	});

	it('can be disabled: no search, no remove buttons', async () => {
		const onSearch = jest.fn(async () => COUNTRIES);
		const { container } = render(
			<Controlled
				start={[COUNTRIES[0]]}
				onSearch={onSearch}
				browse
				disabled
			/>
		);

		expect(
			screen.getByRole('textbox', { name: 'Countries' })
		).toBeDisabled();
		expect(chips(container)).toEqual(['Austria']);
		expect(
			within(
				container.querySelector(
					'.wpo-otd-async-multi-select-selected-options'
				) as HTMLElement
			).queryByRole('button')
		).not.toBeInTheDocument();
		await waitFor(() => expect(onSearch).not.toHaveBeenCalled());
	});

	it('is marked as refused and described by its error', () => {
		render(
			<>
				<p id="err">Choose a value.</p>
				<Controlled invalid describedBy="err" />
			</>
		);

		const input = screen.getByRole('textbox', { name: 'Countries' });
		expect(input).toHaveAttribute('aria-invalid', 'true');
		expect(input).toHaveAccessibleDescription('Choose a value.');
	});
});
