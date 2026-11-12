import React, { useEffect, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useOnClickOutside } from '@sdk/hooks/useOnClickOutside';

export interface AsyncMultiSelectOption {
	id: number | string;
	label: string;
	/** What the results list shows; the label when left out. */
	searchLabel?: string;
	fieldId?: number;
	url?: string;
}

/**
 * One page of results, and whether another follows.
 */
export interface AsyncMultiSelectPage {
	options: AsyncMultiSelectOption[];
	hasMore: boolean;
}

interface AsyncMultiSelectProps {
	placeholder: string;
	/**
	 * The options chosen to start with; with onChange, the options chosen,
	 * followed whenever they change.
	 */
	selectedOptions?: AsyncMultiSelectOption[];
	id?: string;
	className?: string;
	name?: string;
	/**
	 * Find options for what was typed: a list, or a page of a longer one.
	 * Page is 1, then one more for each "Load more".
	 */
	onSearch?: (
		query: string,
		signal: AbortSignal,
		page: number
	) => Promise<AsyncMultiSelectOption[] | AsyncMultiSelectPage>;
	onSelect?: (option: AsyncMultiSelectOption) => void;
	onRemove?: (optionId: AsyncMultiSelectOption['id']) => void;
	/** Makes the choice the caller's: called with the options now chosen. */
	onChange?: (options: AsyncMultiSelectOption[]) => void;
	disabled?: boolean;
	/** Marks the search field as refused. */
	invalid?: boolean;
	/** The id of help or error text describing the field. */
	describedBy?: string;
	/** The search field's accessible name, when no label names it. */
	ariaLabel?: string;
	/** How many may be chosen; the search field goes once that many are. */
	max?: number;
	/** List options as soon as the field is focused, before anything is typed. */
	browse?: boolean;
	/** Typed text can be added as an option of its own. */
	creatable?: boolean;
	/** Why typed text cannot be added, or null when it can. */
	validate?: (text: string) => string | null;
}

const asPage = (
	found: AsyncMultiSelectOption[] | AsyncMultiSelectPage
): AsyncMultiSelectPage =>
	Array.isArray(found) ? { options: found, hasMore: false } : found;

export const AsyncMultiSelectField: React.FC<AsyncMultiSelectProps> = ({
	placeholder = __(
		'Search…',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	),
	selectedOptions = [],
	id,
	className,
	name,
	onSearch,
	onSelect,
	onRemove,
	onChange,
	disabled = false,
	invalid = false,
	describedBy,
	ariaLabel,
	max,
	browse = false,
	creatable = false,
	validate,
}) => {
	const containerRef = useRef<HTMLDivElement>(null);
	const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
	const inputRef = useRef<HTMLInputElement>(null);
	// Read when a search runs, so a caller drawing a new function on each
	// render does not search again.
	const searchRef = useRef(onSearch);
	searchRef.current = onSearch;

	const [showResults, setShowResults] = useState<boolean>(false);
	const [results, setResults] = useState<AsyncMultiSelectOption[]>([]);
	const [hasMore, setHasMore] = useState<boolean>(false);
	const [page, setPage] = useState<number>(1);
	const [kept, setKept] = useState<AsyncMultiSelectOption[]>(selectedOptions);
	const [query, setQuery] = useState<string>('');
	const [loading, setLoading] = useState<boolean>(false);
	// The page being fetched: the list stays shown while a later one loads.
	const [fetching, setFetching] = useState<number>(1);
	// Set while the field is open with nothing typed, in browse mode.
	const [browsing, setBrowsing] = useState<boolean>(false);

	const controlled = undefined !== onChange;
	const selected = controlled ? selectedOptions : kept;
	const typed = query.trim();
	const full = undefined !== max && selected.length >= max;

	useOnClickOutside(containerRef, () => {
		setShowResults(false);
		setBrowsing(false);
	});

	const abortControllerRef = useRef<AbortController | null>(null);

	/**
	 * Fetch a page of results: page 1 replaces them, a later page adds to them.
	 *
	 * @param text   What was typed.
	 * @param number The page.
	 */
	const fetchPage = async (text: string, number: number) => {
		const search = searchRef.current;
		if (!search) {
			return;
		}

		// Cancel any previous request
		abortControllerRef.current?.abort();
		abortControllerRef.current = new AbortController();
		const signal = abortControllerRef.current.signal;

		setLoading(true);
		setFetching(number);
		try {
			setShowResults(true);

			const found = asPage(await search(text, signal, number));

			// Check if request was aborted
			if (signal.aborted) {
				return;
			}

			setResults((was) =>
				1 === number ? found.options : [...was, ...found.options]
			);
			setHasMore(found.hasMore);
			setPage(number);
		} catch (error) {
			// Ignore abort errors
			if (error instanceof Error && error.name === 'AbortError') {
				return;
			}
			console.error('Error fetching results:', error);
		} finally {
			if (!signal.aborted) {
				setLoading(false);
			}
		}
	};

	useEffect(() => {
		if (!searchRef.current || disabled) {
			return;
		}

		if (!typed && !browsing) {
			setResults([]);
			setHasMore(false);
			setShowResults(false);
			return;
		}

		if (debounceRef.current) {
			clearTimeout(debounceRef.current);
		}

		debounceRef.current = setTimeout(() => fetchPage(typed, 1), 300);

		return () => {
			if (debounceRef.current) {
				window.clearTimeout(debounceRef.current);
			}
			// Cancel request on cleanup
			abortControllerRef.current?.abort();
		};
		// fetchPage reads the latest search through a ref.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [typed, browsing, disabled]);

	const choose = (next: AsyncMultiSelectOption[]) => {
		if (onChange) {
			onChange(next);
		} else {
			setKept(next);
		}
	};

	const isChosen = (optionId: AsyncMultiSelectOption['id']) =>
		selected.some((option) => option.id === optionId);

	const handleInputFocus = () => {
		if (browse && !typed) {
			setBrowsing(true);
		}
		if (results.length) {
			setShowResults(true);
		}
	};

	const handleSelectOption = (option: AsyncMultiSelectOption) => {
		if (isChosen(option.id)) {
			return; // Option already selected
		}
		onSelect?.(option);
		choose([...selected, option]);
		setShowResults(false);
		setBrowsing(false);
		setQuery('');
		setResults([]);
		setHasMore(false);
		inputRef.current?.focus();
	};

	const handleRemoveOption = (optionId: AsyncMultiSelectOption['id']) => {
		onRemove?.(optionId);
		choose(selected.filter((option) => option.id !== optionId));
		inputRef.current?.focus();
	};

	// Typed text as an option of its own, when it may be added.
	const refusal = creatable && typed ? (validate?.(typed) ?? null) : null;
	const custom: AsyncMultiSelectOption | null =
		creatable &&
		typed &&
		null === refusal &&
		!isChosen(typed) &&
		!results.some(
			(option) => option.label.toLowerCase() === typed.toLowerCase()
		)
			? { id: typed, label: typed }
			: null;

	const listed = results.filter((option) => !isChosen(option.id));
	const searching = loading && 1 === fetching;
	const options = [...(custom ? [custom] : []), ...listed];
	// Typed text that may be added is offered before any search answers.
	const open = showResults || (creatable && '' !== typed);

	const handleInputKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
		if (e.key === 'Escape') {
			setShowResults(false);
			setBrowsing(false);
			return;
		}

		if (e.key === 'Enter' && creatable) {
			// Never submits a surrounding form.
			e.preventDefault();
			if (custom) {
				handleSelectOption(custom);
			}
			return;
		}

		if (e.key === 'ArrowDown') {
			e.preventDefault();
			const first = containerRef.current?.querySelector(
				'.wpo-otd-async-multi-select-options li'
			) as HTMLElement | null;

			(
				first?.querySelector('button') as HTMLButtonElement | null
			)?.focus();
		}
	};

	const handleOptionKeyDown = (e: React.KeyboardEvent<HTMLButtonElement>) => {
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			(
				e.currentTarget.parentElement?.nextElementSibling?.querySelector(
					'button'
				) as HTMLButtonElement | null
			)?.focus();
		}

		if (e.key === 'ArrowUp') {
			e.preventDefault();
			(
				e.currentTarget.parentElement?.previousElementSibling?.querySelector(
					'button'
				) as HTMLButtonElement | null
			)?.focus();
		}

		if (e.key === 'Escape') {
			setShowResults(false);
			setBrowsing(false);
			inputRef.current?.focus();
		}
	};

	return (
		<div
			ref={containerRef}
			className={`wpo-otd-async-multi-select-container ${className ?? ''}`}
		>
			{!full && (
				<input
					ref={inputRef}
					type="text"
					id={id}
					className={className}
					// No `name`: this is only a search box. Selections are submitted
					// through the hidden `${name}[]` inputs below, so typed text that
					// doesn't match an order is never saved.
					placeholder={placeholder}
					aria-label={ariaLabel}
					aria-invalid={invalid ? true : undefined}
					aria-describedby={describedBy}
					disabled={disabled}
					onFocus={handleInputFocus}
					onChange={(e) => setQuery(e.target.value)}
					value={query}
					onKeyDown={handleInputKeyDown}
				/>
			)}
			<div className="screen-reader-text" aria-live="polite">
				{(() => {
					if (loading) {
						return __(
							'Loading results…',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						);
					}
					if (showResults) {
						return options.length
							? sprintf(
									/* translators: %d is the number of results found. */
									__(
										'%d results found',
										'out-the-door-order-tasks-workflows-for-woocommerce'
									),
									options.length
								)
							: __(
									'No results found',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								);
					}
					return '';
				})()}
			</div>

			{open && null !== refusal && (
				<div className="wpo-otd-async-multi-select-message">
					<p>{refusal}</p>
				</div>
			)}
			{open && null === refusal && searching && (
				<div className="wpo-otd-async-multi-select-message">
					<p>
						{__(
							'Loading…',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)}
					</p>
				</div>
			)}
			{showResults && null === refusal && !loading && !options.length && (
				<div className="wpo-otd-async-multi-select-message">
					<p>
						{__(
							'No results found',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)}
					</p>
				</div>
			)}
			{open &&
				null === refusal &&
				!searching &&
				Boolean(options.length) && (
					<ul className="wpo-otd-async-multi-select-options">
						{options.map((option) => (
							<li
								key={`${option === custom ? 'new' : 'found'}-${option.id}`}
							>
								<button
									type="button"
									onClick={() => handleSelectOption(option)}
									onKeyDown={handleOptionKeyDown}
								>
									{option === custom
										? sprintf(
												/* translators: %s: text typed into a search field. */
												__(
													'Add “%s”',
													'out-the-door-order-tasks-workflows-for-woocommerce'
												),
												option.label
											)
										: (option.searchLabel ?? option.label)}
								</button>
							</li>
						))}
						{hasMore && (
							<li>
								<button
									type="button"
									className="wpo-otd-async-multi-select-more"
									disabled={loading}
									onClick={() => fetchPage(typed, page + 1)}
									onKeyDown={handleOptionKeyDown}
								>
									{__(
										'Load more',
										'out-the-door-order-tasks-workflows-for-woocommerce'
									)}
								</button>
							</li>
						)}
					</ul>
				)}

			<ul className="wpo-otd-async-multi-select-selected-options">
				{selected &&
					selected.map((option) => (
						<li key={option.id}>
							{/* Hidden input for form submission */}
							{name && (
								<input
									type="hidden"
									name={`${name}[]`}
									value={option.id}
								/>
							)}
							{option.url ? (
								<a
									href={option.url}
									target="_blank"
									rel="noopener noreferrer"
								>
									{option.label}
								</a>
							) : (
								option.label
							)}
							{!disabled && (
								<button
									type="button"
									className="wpo-button wpo-button-icon wpo-otd-sidebar-close"
									onClick={() =>
										handleRemoveOption(option.id)
									}
								>
									<span className="screen-reader-text">
										{__(
											'Close',
											'out-the-door-order-tasks-workflows-for-woocommerce'
										)}
									</span>
								</button>
							)}
						</li>
					))}
			</ul>
		</div>
	);
};
