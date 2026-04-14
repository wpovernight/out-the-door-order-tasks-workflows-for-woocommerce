import React from 'react';
import { __ } from '@wordpress/i18n';
import { ToastType } from '@shared/context/ToastContext';

interface ToastProps {
	title: string;
	message: string;
	type: ToastType;
	onClose: () => void;
}

export const Toast: React.FC<ToastProps> = ({
	title,
	message,
	type = ToastType.INFO,
	onClose,
}) => {
	return (
        <div className={`wpo-aom-toast toast-${type}`}>
            <div>
                <strong>{title}</strong>
                {message && <p>{message}</p>}
            </div>
            <button
                type="button"
                className="wpo-button wpo-button-icon close-toast"
                onClick={onClose}
            >
                <span className="screenReader">
                    {__('Close', 'wpo-aom')}
                </span>
            </button>
        </div>
    );
};
