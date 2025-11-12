import React, { useEffect, useRef, useState } from 'react';
import { useOnClickOutside } from '@shared/hooks/useOnClickOutside';

interface Option {
	id: number;
	label: string;
	fieldId?: number;
	url?: string;
}

interface AsyncMultiSelectProps {
	placeholder: string;
	selectedOptions?: Option[];
	id?: string;
	className?: string;
	onSearch?: (term: string) => Promise<Option[]>;
	onSelect?: (option: Option) => void;
	onRemove?: (optionId: number) => void;
}

export const AsyncMultiSelectField: React.FC<AsyncMultiSelectProps> = ({
	placeholder = 'Search...',
	selectedOptions = [],
	id,
	className,
	onSearch,
	onSelect,
	onRemove,
}) => {
	const containerRef = useRef<HTMLDivElement>(null);
	const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

	const [showResults, setShowResults] = useState<boolean>(false);
	const [results, setResults] = useState<Option[]>([]);
	const [selected, setSelected] = useState<Option[]>(selectedOptions);
	const [query, setQuery] = useState<string>('');
	const [loading, setLoading] = useState<boolean>(false);

	useOnClickOutside(containerRef, () => setShowResults(false));

	// Debounced async search with stale-response guard
	useEffect(() => {
		// If no onSearch prop is provided, do nothing
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
			setLoading(true);
			try {
				const results = await onSearch(query);
				setResults(results);
				setShowResults(true);
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Error fetching results:', error);
			} finally {
				setLoading(false);
			}
		}, 300);

		return () => {
			if (debounceRef.current) {
				window.clearTimeout(debounceRef.current);
			}
		};
	}, [query, onSearch]);

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
	};

	const handleRemoveOption = (optionId: number) => {
		onRemove?.(optionId);
		setSelected((prevSelected) =>
			prevSelected.filter((option) => option.id !== optionId)
		);
	};

	return (
		<div
			ref={containerRef}
			className={`wpo-aom-async-multi-select-container ${className ?? ''}`}
		>
			<input
				type="text"
				id={id}
				className={className}
				name={id}
				placeholder={placeholder}
				onFocus={handleInputFocus}
				onChange={(e) => setQuery(e.target.value)}
			/>

			{showResults && (
				<ul className="wpo-aom-async-multi-select-options">
					{loading ? (
						<li>Loading...</li>
					) : Boolean(results.length) ? (
						results.map((option) => (
							<li
								key={option.id}
								onClick={() => handleSelectOption(option)}
							>
								{option.label}
							</li>
						))
					) : (
						// ToDo: translatable string
						<li className="wpo-aom-async-multi-select-no-results">
							No results found
						</li>
					)}
				</ul>
			)}

			<ul className="wpo-aom-async-multi-select-selected-options">
				{selected.map((option) => (
					<li key={option.id}>
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
