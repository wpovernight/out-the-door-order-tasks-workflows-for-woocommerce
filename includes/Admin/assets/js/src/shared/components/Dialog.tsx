import React, { useEffect, useRef } from 'react';

export type DialogVariant = 'confirm' | 'notice';
export type DialogAction = 'info' | 'delete' | 'archive' | 'restore' | 'save';

interface DialogProps {
	title: string;
	message: React.ReactNode;
	variant?: DialogVariant;
	action?: DialogAction;
	confirmText?: string;
	cancelText?: string;
	invertActions?: boolean;
	onConfirm?: () => void;
	onClose: () => void;
}

export const Dialog: React.FC<DialogProps> = ({
	title,
	message,
	variant = 'confirm',
	action = 'info',
	confirmText = 'OK',
	cancelText = 'Cancel',
	invertActions = false,
	onConfirm,
	onClose,
}) => {
	const dialogRef = useRef<HTMLDialogElement>(null);

	useEffect(() => {
		const dialog = dialogRef.current;
		if (!dialog) {
			return;
		}
		dialog.showModal();
		return () => {
			if (dialog.open) {
				dialog.close();
			}
		};
	}, []);

	const handleBackdropClick = (
		event: React.MouseEvent<HTMLDialogElement>
	) => {
		const dialog = dialogRef.current;
		if (!dialog || event.target !== dialog) {
			return;
		}

		const rect = dialog.getBoundingClientRect();
		const clickedOutside =
			event.clientX < rect.left ||
			event.clientX > rect.right ||
			event.clientY < rect.top ||
			event.clientY > rect.bottom;
		if (clickedOutside) {
			onClose();
		}
	};

	const handleConfirm = () => {
		onConfirm?.();
		onClose();
	};

	return (
		<dialog
			ref={dialogRef}
			className={`wpo-aom-dialog dialog-${variant} action-${action}`}
			onClose={onClose}
			onClick={handleBackdropClick}
		>
			<div className="dialog-content">
				<h2>{title}</h2>
				<p>{message}</p>
			</div>
			<ul className="dialog-actions">
				{variant === 'confirm' && (
					<li>
						<button
							type="button"
							className="wpo-button"
							onClick={invertActions ? handleConfirm : onClose}
						>
							{invertActions ? confirmText : cancelText}
						</button>
					</li>
				)}
				<li>
					<button
						type="button"
						className="wpo-button wpo-button-primary"
						onClick={invertActions ? onClose : handleConfirm}
					>
						{invertActions ? cancelText : confirmText}
					</button>
				</li>
			</ul>
		</dialog>
	);
};
