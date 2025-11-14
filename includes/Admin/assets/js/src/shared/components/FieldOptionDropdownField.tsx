import React, { useEffect, useRef, useState } from 'react';
import { FieldOption } from '@shared/types/task';
import { useOnClickOutside } from '@shared/hooks/useOnClickOutside';

interface DropdownProps {
	placeholder: string;
	options: FieldOption[];
	id?: string;
	className?: string;
	selected?: FieldOption;
	onChange?: (option: FieldOption) => void;
}

export const FieldOptionDropdown: React.FC<DropdownProps> = ({
	placeholder = 'Select',
	options,
	className,
	id,
	selected,
	onChange,
}) => {
	const containerRef = useRef<HTMLDivElement>(null);
	const [open, setOpen] = useState<boolean>(false);

	const [selectedOption, setSelectedOption] = useState<FieldOption | null>(
		null
	);

	useEffect(() => {
		setSelectedOption(selected || null);
	}, [selected]);

	const toggleOpen = () => setOpen((prev) => !prev);

	useOnClickOutside(containerRef, () => setOpen(false));

	// Helper function to calculate text color based on background color to keep text readable and accessible.
	const calculateTextColor = (hex?: string): string | undefined => {
		if (!hex) {
			return undefined;
		}
		let c = hex.startsWith('#') ? hex.slice(1) : hex;
		if (c.length === 3) {
			c = c
				.split('')
				.map((ch) => ch + ch)
				.join('');
		}
		if (c.length !== 6) {
			return undefined;
		}
		const r = parseInt(c.slice(0, 2), 16);
		const g = parseInt(c.slice(2, 4), 16);
		const b = parseInt(c.slice(4, 6), 16);
		const brightness = (299 * r + 587 * g + 114 * b) / 1000;
		return brightness > 128 ? '#111' : '#fff';
	};

	const applyStyle = (option?: FieldOption) => {
		const backgroundColor = option?.color || undefined;
		const textColor = calculateTextColor(backgroundColor);
		return backgroundColor
			? ({
					backgroundColor,
					color: textColor,
				} as React.CSSProperties)
			: {};
	};

	const handleSelect = (option: FieldOption) => {
		onChange?.(option);
		setOpen(false);
		setSelectedOption(option);
	};

	return (
		<div
			ref={containerRef}
			className="wpo-aom-field-option-dropdown-container"
		>
			<button
				type="button"
				id={id}
				className={`${className ? className : ''} ${open ? 'open' : ''}`}
				onClick={toggleOpen}
			>
				<span
					className="wpo-aom-label"
					style={selectedOption ? applyStyle(selectedOption) : {}}
				>
					{selectedOption ? selectedOption.label : placeholder}
				</span>
			</button>
			{open && (
				<ul id={id ? `${id}-options` : undefined}>
					{options.map((option) => (
						// eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions
						<li
							key={option.id}
							className={`${
								selectedOption?.slug === option.slug
									? 'selected'
									: ''
							}`}
							onClick={() => handleSelect(option)}
							onKeyDown={(e) => {
								if (e.key === 'Enter' || e.key === ' ') {
									e.preventDefault();
									handleSelect(option);
								}
							}}
							tabIndex={0}
						>
							<span
								className="wpo-aom-label"
								style={applyStyle(option)}
							>
								{option.label}
							</span>
						</li>
					))}
				</ul>
			)}
		</div>
	);
};
