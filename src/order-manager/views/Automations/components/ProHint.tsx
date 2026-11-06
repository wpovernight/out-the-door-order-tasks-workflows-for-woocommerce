import React, { useId, useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * The props a control spreads to show the hint while hovered or focused.
 */
export interface ProHintControlProps {
	'aria-describedby': string;
	'aria-disabled': true;
	onMouseEnter: () => void;
	onMouseLeave: () => void;
	onFocus: () => void;
	onBlur: () => void;
	onKeyDown: (event: React.KeyboardEvent) => void;
}

interface ProHintProps {
	/** Renders the control, given the props that tie it to the hint. */
	children: (props: ProHintControlProps) => React.ReactNode;
	className?: string;
}

/**
 * A disabled control with an "Available in Pro" hint.
 *
 * The control stays focusable (aria-disabled, not disabled), so keyboard users
 * reach it and hear the hint as its description. The hint shows while the
 * control is hovered or focused; Escape hides it until the next hover or focus.
 *
 * @param props
 * @param props.children
 * @param props.className
 */
export const ProHint = ({ children, className = '' }: ProHintProps) => {
	const hintId = useId();
	const [isHovered, setIsHovered] = useState(false);
	const [isFocused, setIsFocused] = useState(false);
	const [isDismissed, setIsDismissed] = useState(false);

	const isVisible = (isHovered || isFocused) && !isDismissed;

	return (
		<span className={`automations-pro-hint ${className}`.trim()}>
			{children({
				'aria-describedby': hintId,
				'aria-disabled': true,
				onMouseEnter: () => {
					setIsHovered(true);
					setIsDismissed(false);
				},
				onMouseLeave: () => setIsHovered(false),
				onFocus: () => {
					setIsFocused(true);
					setIsDismissed(false);
				},
				onBlur: () => setIsFocused(false),
				onKeyDown: (event) => {
					if (event.key === 'Escape') {
						setIsDismissed(true);
					}
				},
			})}
			<span
				id={hintId}
				role="tooltip"
				className={`automations-pro-hint-bubble${
					isVisible ? ' is-visible' : ''
				}`}
			>
				{__(
					'Available in Pro',
					'out-the-door-order-tasks-workflows-for-woocommerce'
				)}
			</span>
		</span>
	);
};
