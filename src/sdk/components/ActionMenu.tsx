import React, {
	useCallback,
	useEffect,
	useId,
	useLayoutEffect,
	useRef,
	useState,
} from 'react';
import { createPortal } from 'react-dom';

/**
 * One choice of an action menu.
 */
export interface ActionMenuItem {
	/** Unique within the menu. */
	key: string;
	label: string;
	/** A line under the label saying what it does. */
	description?: string;
	/** Drawn as a destructive choice, such as Delete. */
	danger?: boolean;
	onSelect: () => void;
}

export interface ActionMenuProps {
	/** The menu button's accessible name, e.g. "Group 1 options". */
	label: string;
	items: ActionMenuItem[];
	/** What the button shows; three dots when left out. */
	buttonContent?: React.ReactNode;
	/** The button's classes; an icon button with three dots by default. */
	buttonClassName?: string;
	/** A small heading above the choices. */
	heading?: string;
	/** A note under the choices. */
	note?: React.ReactNode;
	/** Which edge of the button the menu lines up with. */
	align?: 'start' | 'end';
	/** Extra classes on the menu, e.g. to make it wider. */
	menuClassName?: string;
	disabled?: boolean;
}

const GAP = 4;
const MARGIN = 8;

/**
 * A button that opens a floating menu of choices, each with an optional
 * description. The menu is drawn on <body>, so no ancestor clips it.
 * Arrow keys, Home and End move between choices; Escape closes it and focus
 * goes back to the button; a click outside or Tab closes it.
 *
 * @param props
 * @param props.label
 * @param props.items
 * @param props.buttonContent
 * @param props.buttonClassName
 * @param props.heading
 * @param props.note
 * @param props.align
 * @param props.menuClassName
 * @param props.disabled
 */
export const ActionMenu: React.FC<ActionMenuProps> = ({
	label,
	items,
	buttonContent,
	buttonClassName = 'wpo-button wpo-button-icon wpo-otd-action-menu-dots',
	heading,
	note,
	align = 'end',
	menuClassName = '',
	disabled = false,
}) => {
	const [open, setOpen] = useState(false);
	const [style, setStyle] = useState<React.CSSProperties>({
		position: 'fixed',
		visibility: 'hidden',
	});
	const buttonRef = useRef<HTMLButtonElement>(null);
	const menuRef = useRef<HTMLUListElement>(null);
	const menuId = useId();

	const choices = (): HTMLButtonElement[] =>
		Array.from(
			menuRef.current?.querySelectorAll<HTMLButtonElement>(
				'[role="menuitem"]'
			) ?? []
		);

	const close = useCallback((refocus: boolean) => {
		setOpen(false);
		if (refocus) {
			buttonRef.current?.focus();
		}
	}, []);

	const place = useCallback(() => {
		const button = buttonRef.current;
		const menu = menuRef.current;
		if (!button || !menu) {
			return;
		}

		const anchor = button.getBoundingClientRect();
		const size = menu.getBoundingClientRect();
		const below = window.innerHeight - anchor.bottom;
		const upward = below < size.height + GAP && anchor.top > below;

		let top = upward ? anchor.top - size.height - GAP : anchor.bottom + GAP;
		top = Math.min(
			Math.max(top, MARGIN),
			window.innerHeight - size.height - MARGIN
		);

		let left = 'end' === align ? anchor.right - size.width : anchor.left;
		left = Math.min(
			Math.max(left, MARGIN),
			window.innerWidth - size.width - MARGIN
		);

		setStyle({ position: 'fixed', top, left });
	}, [align]);

	useLayoutEffect(() => {
		if (open) {
			place();
			choices()[0]?.focus();
		}
	}, [open, place]);

	useEffect(() => {
		if (!open) {
			return;
		}

		const outside = (event: MouseEvent) => {
			const target = event.target as Node;
			if (
				!buttonRef.current?.contains(target) &&
				!menuRef.current?.contains(target)
			) {
				close(false);
			}
		};

		document.addEventListener('mousedown', outside);
		window.addEventListener('scroll', place, true);
		window.addEventListener('resize', place);
		return () => {
			document.removeEventListener('mousedown', outside);
			window.removeEventListener('scroll', place, true);
			window.removeEventListener('resize', place);
		};
	}, [open, place, close]);

	useEffect(() => {
		if (disabled && open) {
			setOpen(false);
		}
	}, [disabled, open]);

	const onMenuKeyDown = (event: React.KeyboardEvent<HTMLUListElement>) => {
		const list = choices();
		const active = menuRef.current?.ownerDocument.activeElement;
		const at = list.indexOf(active as HTMLButtonElement);
		let next: HTMLButtonElement | undefined;

		switch (event.key) {
			case 'ArrowDown':
				next = list[(at + 1) % list.length];
				break;
			case 'ArrowUp':
				next = list[(at - 1 + list.length) % list.length];
				break;
			case 'Home':
				next = list[0];
				break;
			case 'End':
				next = list[list.length - 1];
				break;
			case 'Escape':
				event.preventDefault();
				event.stopPropagation();
				close(true);
				return;
			case 'Tab':
				close(false);
				return;
			default:
				return;
		}

		event.preventDefault();
		next?.focus();
	};

	const choose = (item: ActionMenuItem) => {
		close(true);
		item.onSelect();
	};

	return (
		<>
			<button
				ref={buttonRef}
				type="button"
				className={buttonClassName}
				aria-haspopup="menu"
				aria-expanded={open}
				aria-controls={open ? menuId : undefined}
				aria-label={buttonContent ? undefined : label}
				disabled={disabled}
				onClick={() => setOpen((was) => !was)}
			>
				{buttonContent}
			</button>
			{open &&
				createPortal(
					<ul
						ref={menuRef}
						id={menuId}
						role="menu"
						aria-label={label}
						className={`wpo-action-menu wpo-action-menu-floating wpo-otd-action-menu ${menuClassName}`}
						style={style}
						onKeyDown={onMenuKeyDown}
					>
						{heading && (
							<li
								role="presentation"
								className="wpo-otd-action-menu-heading"
							>
								{heading}
							</li>
						)}
						{items.map((item) => (
							<li key={item.key} role="none">
								<button
									type="button"
									role="menuitem"
									className={
										item.danger ? 'is-danger' : undefined
									}
									onClick={() => choose(item)}
								>
									<span className="wpo-otd-action-menu-label">
										{item.label}
									</span>
									{item.description && (
										<span className="wpo-otd-action-menu-description">
											{item.description}
										</span>
									)}
								</button>
							</li>
						))}
						{note && (
							<li
								role="presentation"
								className="wpo-otd-action-menu-note"
							>
								{note}
							</li>
						)}
					</ul>,
					document.body
				)}
		</>
	);
};
