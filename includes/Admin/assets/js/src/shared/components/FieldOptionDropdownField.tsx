import React, { useEffect, useMemo, useRef, useState } from 'react';
import { FieldOption } from '@shared/types/task';
import { useOnClickOutside } from '@shared/hooks/useOnClickOutside';

// Helper function to calculate text color based on background color to keep text readable and accessible.
const calculateTextColor = (hex?: string): string => {
	if (!hex) {
		return '#000';
	}

	let c = hex.replace('#', '');

	if (c.length === 3) {
		c = c
			.split('')
			.map((ch) => ch + ch)
			.join('');
	}

	// Validate hex
	if (!/^[0-9A-Fa-f]{6}$/.test(c)) {
		return '#000';
	}

	const r = parseInt(c.slice(0, 2), 16);
	const g = parseInt(c.slice(2, 4), 16);
	const b = parseInt(c.slice(4, 6), 16);

	const brightness = (299 * r + 587 * g + 114 * b) / 1000;
	return brightness > 128 ? '#000' : '#fff';
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

interface DropdownProps {
	placeholder: string;
	options: FieldOption[];
	id?: string;
	className?: string;
	name?: string;
	selected?: FieldOption;
	onChange?: (option: FieldOption) => void;
}

export const FieldOptionDropdown: React.FC<DropdownProps> = ({
	placeholder = 'Select',
	options,
	className,
	name,
	id,
	selected,
	onChange,
}) => {
	const containerRef = useRef<HTMLDivElement>(null);
    const triggerButtonRef = useRef<HTMLButtonElement>(null);

    const [open, setOpen] = useState<boolean>(false);
	const [selectedOption, setSelectedOption] = useState<FieldOption | null>(
		selected ?? null
	);

	useEffect(() => {
		setSelectedOption(selected || null);
	}, [selected]);

	const toggleOpen = () => setOpen((prev) => !prev);

	useOnClickOutside(containerRef, () => setOpen(false));

	const selectedStyle = useMemo(
		() => (selectedOption ? applyStyle(selectedOption) : {}),
		[selectedOption]
	);

	const handleSelect = (option: FieldOption) => {
		onChange?.(option);
		setOpen(false);
		setSelectedOption(option);
        triggerButtonRef.current?.focus();
	};

	const handleButtonKeyDown = (e: React.KeyboardEvent) => {
		if (e.key === 'Escape' && open) {
			setOpen(false);
		}

		if ((e.key === 'Enter' || e.key === ' ') && !open) {
			e.preventDefault();
			setOpen(true);
		}

		if (open && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
			e.preventDefault();
			const firstButton = containerRef.current?.querySelector(
				'ul li button'
			) as HTMLButtonElement | null;
			firstButton?.focus();
		}
	};

	const handleOptionKeyDown = (e: React.KeyboardEvent<HTMLButtonElement>) => {
		if (e.key === 'Escape') {
			setOpen(false);
		}

		if (e.key === 'ArrowDown') {
			e.preventDefault();
			const next =
				e.currentTarget.parentElement?.nextElementSibling?.querySelector(
					'button'
				);
			if (next) {
				next.focus();
			} else {
				const first = containerRef.current?.querySelector(
					'ul li:first-child button'
				) as HTMLButtonElement | null;
				first?.focus();
			}
		}

		if (e.key === 'ArrowUp') {
			e.preventDefault();
			const prevButton =
				e.currentTarget.parentElement?.previousElementSibling?.querySelector(
					'button'
				) as HTMLButtonElement | null;

			if (prevButton) {
				prevButton.focus();
			} else {
				const lastButton = containerRef.current?.querySelector(
					'ul li:last-child button'
				) as HTMLButtonElement | null;
				lastButton?.focus();
			}
		}
	};

	return (
		<div
			ref={containerRef}
			className="wpo-aom-field-option-dropdown-container"
		>
			{name && (
				<input
					type="hidden"
					name={name}
					value={selectedOption?.id || ''}
				/>
			)}
			<button
                ref={triggerButtonRef}
				type="button"
				id={id}
				className={[className, open && 'open']
					.filter(Boolean)
					.join(' ')}
				onClick={toggleOpen}
				onKeyDown={handleButtonKeyDown}
			>
				<span className="wpo-aom-label" style={selectedStyle}>
					{selectedOption ? selectedOption.label : placeholder}
				</span>
			</button>
			{open && (
				<ul id={id ? `${id}-options` : undefined}>
					{options.map((option) => (
						<li key={option.id}>
							<button
								type="button"
								onClick={() => handleSelect(option)}
								onKeyDown={handleOptionKeyDown}
							>
								<span
									className="wpo-aom-label"
									style={applyStyle(option)}
								>
									{option.label}
								</span>
							</button>
						</li>
					))}
				</ul>
			)}
		</div>
	);
};
