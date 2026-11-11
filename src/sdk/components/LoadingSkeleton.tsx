import React, { CSSProperties, ReactNode } from 'react';
import { __ } from '@wordpress/i18n';

interface SkeletonLineProps {
	width?: string;
	height?: string;
	className?: string;
	style?: CSSProperties;
}

/**
 * Basic skeleton line/box element with shimmer effect.
 *
 * @param {Object}        props
 * @param {string}        [props.width='100%'] - Width of the skeleton line
 * @param {string}        [props.height='1em'] - Height of the skeleton line
 * @param {string}        [props.className]    - Additional CSS classes
 * @param {CSSProperties} [props.style]        - Inline styles
 */
export const SkeletonLine: React.FC<SkeletonLineProps> = ({
	width = '100%',
	height = '1em',
	className = '',
	style = {},
}) => {
	return (
		<div
			className={`wpo-otd-skeleton-line ${className}`}
			style={{ width, height, ...style }}
		/>
	);
};

interface SkeletonBoxProps {
	width?: string;
	height?: string;
	className?: string;
	style?: CSSProperties;
	children?: React.ReactNode;
}

/**
 * Container for skeleton content with shimmer animation.
 *
 * @param {Object}        props
 * @param {string}        [props.width='100%']  - Width of the skeleton box
 * @param {string}        [props.height='auto'] - Height of the skeleton box
 * @param {string}        [props.className]     - Additional CSS classes
 * @param {CSSProperties} [props.style]         - Inline styles
 * @param {ReactNode}     [props.children]      - Child elements (skeleton lines, etc.)
 */
export const SkeletonBox: React.FC<SkeletonBoxProps> = ({
	width = '100%',
	height = 'auto',
	className = '',
	style = {},
	children,
}) => {
	return (
		<div
			className={`wpo-otd-skeleton-box ${className}`}
			style={{ width, height, ...style }}
		>
			{children}
		</div>
	);
};

interface LoadingSkeletonProps {
	count?: number;
	className?: string;
	children: React.ReactNode;
}

/**
 * Generic loading skeleton container that renders children multiple times.
 * Use this to wrap any custom skeleton layout.
 *
 * @param {Object}    props
 * @param {number}    [props.count=1]   - Number of skeleton items to render
 * @param {string}    [props.className] - Additional CSS classes
 * @param {ReactNode} props.children    - Child elements representing the skeleton layout
 *
 * @example
 * <LoadingSkeleton count={3}>
 *   <SkeletonBox height="100px">
 *     <SkeletonLine width="60%" height="16px" />
 *     <SkeletonLine width="100%" />
 *     <SkeletonLine width="40%" />
 *   </SkeletonBox>
 * </LoadingSkeleton>
 */
export const LoadingSkeleton: React.FC<LoadingSkeletonProps> = ({
	count = 1,
	className = '',
	children,
}) => {
	return (
		<div className={`wpo-otd-loading-skeleton-container ${className}`}>
			{Array.from({ length: count }).map((_, index) => (
				<React.Fragment key={index}>{children}</React.Fragment>
			))}
		</div>
	);
};

interface EmptyStateProps {
	icon?: 'note' | 'box' | 'rules' | string;
	/** A bold first line; the message is then shown under it as its description. */
	title?: string;
	message: string;
	actionText?: string;
	onAction?: () => void;
	/** Shows the action as a primary or secondary button instead of the default blue one. */
	actionVariant?: 'primary' | 'secondary';
	actionButtonProps?: React.ButtonHTMLAttributes<HTMLButtonElement>;
}

/**
 * Empty state component for when there are no items.
 *
 * @param {Object}   props
 * @param {string}   [props.icon='note']       - Icon name ('note', 'box', 'rules') or emoji string
 * @param {string}   [props.title]             - Bold first line; the message becomes its description
 * @param {string}   props.message             - Message to display (required)
 * @param {string}   [props.actionText]        - Text for the action button
 * @param {Function} [props.onAction]          - Callback when action button is clicked
 * @param {string}   [props.actionVariant]     - 'primary' or 'secondary' button instead of the default one
 * @param {Object}   [props.actionButtonProps] - Additional props for the action button (data attributes, etc.)
 */
export const EmptyState: React.FC<EmptyStateProps> = ({
	icon = 'note',
	title,
	message,
	actionText,
	onAction,
	actionVariant,
	actionButtonProps,
}) => {
	// Extract className from actionButtonProps to merge with default
	const { className: customClassName, ...restButtonProps } =
		actionButtonProps || {};
	const baseButtonClassName = actionVariant
		? `wpo-button wpo-button-${actionVariant}`
		: 'wpo-otd-empty-state-action';
	const buttonClassName = customClassName
		? `${baseButtonClassName} ${customClassName}`
		: baseButtonClassName;

	const isSvgIcon = icon === 'note' || icon === 'box' || icon === 'rules';

	return (
		<div className="wpo-otd-empty-state">
			<div
				className={`wpo-otd-empty-state-icon${isSvgIcon ? ` wpo-otd-empty-state-icon--${icon}` : ''}`}
			>
				{!isSvgIcon && icon}
			</div>
			{title && <p className="wpo-otd-empty-state-title">{title}</p>}
			<p
				className={`wpo-otd-empty-state-message${title ? ' wpo-otd-empty-state-description' : ''}`}
			>
				{message}
			</p>
			{actionText && onAction && (
				<button
					type="button"
					className={buttonClassName}
					onClick={() => onAction()}
					{...restButtonProps}
				>
					{actionText}
				</button>
			)}
		</div>
	);
};

interface ErrorStateProps {
	message: string;
	onRetry?: () => void;
	retryText?: string;
}

/**
 * Error state component for when loading fails.
 *
 * @param {Object}   props
 * @param {string}   props.message                 - Error message to display (required)
 * @param {Function} [props.onRetry]               - Callback when retry button is clicked
 * @param {string}   [props.retryText='Try Again'] - Text for the retry button
 */
export const ErrorState: React.FC<ErrorStateProps> = ({
	message,
	onRetry,
	retryText = __(
		'Try Again',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	),
}: {
	message: string;
	onRetry?: Function;
	retryText?: string;
}) => {
	return (
		<div className="wpo-otd-error-state">
			<div className="wpo-otd-error-state-icon">⚠️</div>
			<p className="wpo-otd-error-state-message">{message}</p>
			{onRetry && (
				<button
					type="button"
					className="wpo-otd-error-state-retry"
					onClick={() => onRetry()}
				>
					{retryText}
				</button>
			)}
		</div>
	);
};
