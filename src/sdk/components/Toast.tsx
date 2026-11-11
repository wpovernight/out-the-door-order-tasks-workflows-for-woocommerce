import React, { useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { ToastType } from '@sdk/context/ToastContext';
import type { ToastAction } from '@sdk/context/ToastContext';

interface ToastProps {
	title: string;
	message: string;
	type: ToastType;
	isExiting?: boolean;
	/** A button beside the close button; using it also closes the toast. */
	action?: ToastAction;
	onClose: () => void;
}

export const Toast: React.FC<ToastProps> = ({
	title,
	message,
	type = ToastType.INFO,
	isExiting = false,
	action,
	onClose,
}) => {
	// Run once only: a key pressed again while the toast fades out would run it again.
	const actionUsed = useRef(false);

	return (
		<div
			className={`wpo-otd-toast toast-${type}${isExiting ? ' is-exiting' : ''}`}
		>
			<div>
				<div className="toast-content">
					<span className="toast-title">{title}</span>
					{message && <p>{message}</p>}
				</div>
				{action && (
					<button
						type="button"
						className="wpo-button toast-action"
						disabled={isExiting}
						onClick={() => {
							if (actionUsed.current) {
								return;
							}
							actionUsed.current = true;
							action.onClick();
							onClose();
						}}
					>
						{action.label}
					</button>
				)}
				<button
					type="button"
					className="wpo-button wpo-button-icon close-toast"
					onClick={onClose}
				>
					<span className="screen-reader-text">
						{__(
							'Close',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)}
					</span>
				</button>
			</div>
		</div>
	);
};
