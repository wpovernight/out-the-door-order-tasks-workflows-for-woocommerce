import React, { useRef, useState } from 'react';
import { FieldOption } from '@shared/types/task';
import { useOnClickOutside } from '@shared/hooks/useOnClickOutside';

interface DropdownProps {
	value: string;
	options: FieldOption[];
	className?: string;
	id?: string;
	selectedSlug?: string;
	onChange?: (value: string | number) => void;
}

export const FieldOptionDropdown: React.FC<DropdownProps> = ({
	value,
	options,
	className,
	id,
	selectedSlug,
	onChange,
}) => {
	const containerRef = useRef<HTMLDivElement>(null);
	const [open, setOpen] = useState<boolean>(false);
	const selected = options.find((option) => option.slug === selectedSlug);
	const toggleOpen = () => setOpen((prev) => !prev);

	const handleClickOutside = () => {
		setOpen(false);
	};
	useOnClickOutside(containerRef, handleClickOutside);

	return (
		<div
			ref={containerRef}
			className="wpo-aom-field-option-dropdown-container"
		>
			<button
				type="button"
				className={`${className ? className : ''} ${open ? 'open' : ''}`}
				id={id}
				onClick={toggleOpen}
			>
				<span
					style={{
						backgroundColor:
							selected && selected.color ? selected.color : '',
					}}
				>
					{selected ? selected.label : value}
				</span>
			</button>
			{open && (
				<ul id={id ? `${id}-options` : undefined}>
					{options.map((option) => (
						// eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions
						<li
							key={option.id}
							className={`${selectedSlug === option.slug ? 'selected' : ''}`}
							onClick={() => {
								if (onChange) {
									onChange(option.slug);
								}
								setOpen(false);
							}}
							onKeyDown={(e) => {
								if (e.key === 'Enter' || e.key === ' ') {
									e.preventDefault();
									if (onChange) {
										onChange(option.slug);
									}
									setOpen(false);
								}
							}}
							tabIndex={0}
						>
							<span
								style={{
									backgroundColor: option.color
										? option.color
										: '',
								}}
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
