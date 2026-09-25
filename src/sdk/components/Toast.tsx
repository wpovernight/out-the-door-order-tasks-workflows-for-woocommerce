import React from 'react';
import { __ } from '@wordpress/i18n';
import { ToastType } from '@sdk/context/ToastContext';

interface ToastProps {
	title: string;
	message: string;
	type: ToastType;
	isExiting?: boolean;
	onClose: () => void;
}

export const Toast: React.FC<ToastProps> = ({
	title,
	message,
	type = ToastType.INFO,
	isExiting = false,
	onClose,
}) => {
	return (
		<div
			className={`wpo-aom-toast toast-${type}${isExiting ? ' is-exiting' : ''}`}
		>
			<div>
				<div className="toast-content">
					<span className="toast-title">{title}</span>
					{message && <p>{message}</p>}
				</div>
				<button
					type="button"
					className="wpo-button wpo-button-icon close-toast"
					onClick={onClose}
				>
					<span className="screen-reader-text">
						{__('Close', 'out-the-door-order-tasks-workflows-for-woocommerce')}
					</span>
				</button>
			</div>
		</div>
	);
};
