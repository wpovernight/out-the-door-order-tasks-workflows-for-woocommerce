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
 * reach it and hear the hint as its description. Escape hides the hint.
 *
 * @param props
 * @param props.children
 * @param props.className
 */
export const ProHint = ({ children, className = '' }: ProHintProps) => {
	const hintId = useId();
	const [isVisible, setIsVisible] = useState(false);

	const show = () => setIsVisible(true);
	const hide = () => setIsVisible(false);

	return (
		<span className={`automations-pro-hint ${className}`.trim()}>
			{children({
				'aria-describedby': hintId,
				'aria-disabled': true,
				onMouseEnter: show,
				onMouseLeave: hide,
				onFocus: show,
				onBlur: hide,
				onKeyDown: (event) => {
					if (event.key === 'Escape') {
						hide();
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
