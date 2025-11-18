import React, { useEffect, useRef, useState } from 'react';
import { useOnClickOutside } from '@shared/hooks/useOnClickOutside';

interface Option {
	id: number;
	label: string;
	searchLabel?: string;
	fieldId?: number;
	url?: string;
}

interface AsyncMultiSelectProps {
	placeholder: string;
	selectedOptions?: Option[];
	id?: string;
	className?: string;
	name?: string;
	onSearch?: (query: string, signal: AbortSignal) => Promise<Option[]>;
	onSelect?: (option: Option) => void;
	onRemove?: (optionId: number) => void;
}

export const AsyncMultiSelectField: React.FC<AsyncMultiSelectProps> = ({
	placeholder = 'Search...',
	selectedOptions = [],
	id,
	className,
	name,
	onSearch,
	onSelect,
	onRemove,
}) => {
	const containerRef = useRef<HTMLDivElement>(null);
	const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
	const inputRef = useRef<HTMLInputElement>(null);

	const [showResults, setShowResults] = useState<boolean>(false);
	const [results, setResults] = useState<Option[]>([]);
	const [selected, setSelected] = useState<Option[]>(selectedOptions);
	const [query, setQuery] = useState<string>('');
	const [loading, setLoading] = useState<boolean>(false);

	useOnClickOutside(containerRef, () => setShowResults(false));

	const abortControllerRef = useRef<AbortController | null>(null);

	useEffect(() => {
		if (!onSearch) {
			return;
		}

		if (!query.trim()) {
			setResults([]);
			setShowResults(false);
			return;
		}

		if (debounceRef.current) {
			clearTimeout(debounceRef.current);
		}

		debounceRef.current = setTimeout(async () => {
			// Cancel any previous request
			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
			}

			// Create new abort controller for this request
			abortControllerRef.current = new AbortController();
			const signal = abortControllerRef.current.signal;

			setLoading(true);
			try {
				setShowResults(true);

				const searchResults = await onSearch(query, signal);

				// Check if request was aborted
				if (signal.aborted) {
					return;
				}

				const filteredResults = searchResults.filter(
					(result) =>
						!selected.find((option) => option.id === result.id)
				);

				setResults(filteredResults);
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
		}, 300);

		return () => {
			if (debounceRef.current) {
				window.clearTimeout(debounceRef.current);
			}
			// Cancel request on cleanup
			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
			}
		};
	}, [query, onSearch, selected]);

	const handleInputFocus = () => {
		if (results.length) {
			setShowResults(true);
		}
	};

	const handleSelectOption = (option: Option) => {
		if (
			selected.find((selectedOption) => selectedOption.id === option.id)
		) {
			return; // Option already selected
		}
		onSelect?.(option);
		setSelected((prevSelected) => [...prevSelected, option]);
		setShowResults(false);
		setQuery('');
		setResults([]);
		inputRef.current?.focus();
	};

	const handleRemoveOption = (optionId: number) => {
		onRemove?.(optionId);
		setSelected((prevSelected) =>
			prevSelected.filter((option) => option.id !== optionId)
		);
		inputRef.current?.focus();
	};

	const handleInputKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
		if (e.key === 'Escape') {
			setShowResults(false);
			return;
		}

		if (e.key === 'ArrowDown') {
			e.preventDefault();
			const first = containerRef.current?.querySelector(
				'.wpo-aom-async-multi-select-options li'
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
			inputRef.current?.focus();
		}
	};

	return (
		<div
			ref={containerRef}
			className={`wpo-aom-async-multi-select-container ${className ?? ''}`}
		>
			<input
				ref={inputRef}
				type="text"
				id={id}
				className={className}
				name={name}
				placeholder={placeholder}
				onFocus={handleInputFocus}
				onChange={(e) => setQuery(e.target.value)}
				value={query}
				onKeyDown={handleInputKeyDown}
			/>
			<div className="screenReader" aria-live="polite">
				{(() => {
					if (loading) {
						return 'Loading results...';
					}
					if (showResults) {
						return results.length
							? `${results.length} results found`
							: 'No results found';
					}
					return '';
				})()}
			</div>

			{showResults && loading && (
				<div className="wpo-aom-async-multi-select-message">
					<p>Loading...</p>
				</div>
			)}
			{showResults && !loading && !Boolean(results.length) && (
				// ToDo: translatable string
				<div className="wpo-aom-async-multi-select-message">
					<p>No results found</p>
				</div>
			)}
			{showResults && !loading && Boolean(results.length) && (
				<ul className="wpo-aom-async-multi-select-options">
					{results.map((option) => (
						<li key={option.id}>
							<button
								type="button"
								onClick={() => handleSelectOption(option)}
								onKeyDown={handleOptionKeyDown}
							>
								{option.searchLabel}
							</button>
						</li>
					))}
				</ul>
			)}

			<ul className="wpo-aom-async-multi-select-selected-options">
				{selected.map((option) => (
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
						<button
							type="button"
							className="wpo-button wpo-button-icon wpo-aom-sidebar-close"
							onClick={() => handleRemoveOption(option.id)}
						>
							<span className="screenReader">Close</span>
						</button>
					</li>
				))}
			</ul>
		</div>
	);
};
