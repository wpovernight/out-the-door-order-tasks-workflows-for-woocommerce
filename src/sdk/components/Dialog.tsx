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
	/**
	 * A third choice, offered first as the main one, the other two stacked
	 * under it; none without it.
	 */
	alternativeText?: string;
	onConfirm?: () => void;
	onAlternative?: () => void;
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
	alternativeText,
	onConfirm,
	onAlternative,
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

	const handleAlternative = () => {
		onAlternative?.();
		onClose();
	};

	// With a third choice, it is the main one: the other two are plain.
	const primaryClass = alternativeText
		? 'wpo-button'
		: 'wpo-button wpo-button-primary';

	return (
		// eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-noninteractive-element-interactions
		<dialog
			ref={dialogRef}
			className={`wpo-otd-dialog dialog-${variant} action-${action}`}
			onClose={onClose}
			onClick={handleBackdropClick}
		>
			<div className="dialog-content">
				<h2>{title}</h2>
				<p>{message}</p>
			</div>
			<ul
				className={
					alternativeText
						? 'dialog-actions dialog-actions-stacked'
						: 'dialog-actions'
				}
			>
				{alternativeText && (
					<li>
						<button
							type="button"
							className="wpo-button wpo-button-primary"
							onClick={handleAlternative}
						>
							{alternativeText}
						</button>
					</li>
				)}
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
						className={primaryClass}
						onClick={invertActions ? onClose : handleConfirm}
					>
						{invertActions ? cancelText : confirmText}
					</button>
				</li>
			</ul>
		</dialog>
	);
};
