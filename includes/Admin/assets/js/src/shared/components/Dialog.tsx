import React, { useEffect, useRef } from 'react';

export type DialogVariant = 'confirm' | 'notice';
export type DialogTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

interface DialogProps {
	title: string;
	message: string;
	variant?: DialogVariant;
	tone?: DialogTone;
	confirmText?: string;
	cancelText?: string;
	onConfirm?: () => void;
	onClose: () => void;
}

export const Dialog: React.FC<DialogProps> = ({
	title,
	message,
	variant = 'confirm',
	tone = 'neutral',
	confirmText = 'OK',
	cancelText = 'Cancel',
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
			className={`wpo-aom-dialog dialog-${variant} tone-${tone}`}
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
							onClick={onClose}
						>
							{cancelText}
						</button>
					</li>
				)}
				<li>
					<button
						type="button"
						className="wpo-button wpo-button-primary"
						onClick={handleConfirm}
					>
						{confirmText}
					</button>
				</li>
			</ul>
		</dialog>
	);
};
