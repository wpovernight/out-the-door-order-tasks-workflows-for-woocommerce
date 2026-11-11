import React, {
	createContext,
	useCallback,
	useContext,
	useRef,
	useState,
} from 'react';
import { Toast } from '@sdk/components/Toast';

export enum ToastType {
	SUCCESS = 'success',
	ERROR = 'error',
	INFO = 'info',
}

export enum ToastDuration {
	SHORT = 3000,
	STANDARD = 5000,
	LONG = 10000,
	PERSISTENT = 0,
}

/**
 * A button shown in a toast beside its close button, e.g. Undo. Using it
 * also closes the toast.
 */
export interface ToastAction {
	label: string;
	onClick: () => void;
}

export interface ToastOptions {
	title: string;
	message?: string;
	type?: ToastType;
	duration?: ToastDuration;
	action?: ToastAction;
}

interface ToastNotification
	extends
		Required<Omit<ToastOptions, 'action'>>,
		Pick<ToastOptions, 'action'> {
	id: number;
	isExiting: boolean;
}

const TOAST_FADE_OUT_MS = 400;

interface ToastContextType {
	/** Show a toast; returns its id, for dismissToast(). */
	addToast: (toast: ToastOptions) => number;
	/** Close a toast early; an id already closed is ignored. */
	dismissToast: (id: number) => void;
}

const ToastContext = createContext<ToastContextType | undefined>(undefined);

export const ToastProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const [toasts, setToasts] = useState<ToastNotification[]>([]);
	const nextId = useRef(0);

	const dismissToast = useCallback((id: number) => {
		setToasts((prev) =>
			prev.map((toast) =>
				toast.id === id ? { ...toast, isExiting: true } : toast
			)
		);
		setTimeout(() => {
			setToasts((prev) => prev.filter((toast) => toast.id !== id));
		}, TOAST_FADE_OUT_MS);
	}, []);

	const addToast = useCallback(
		(options: ToastOptions) => {
			const id = nextId.current++;
			const toast: ToastNotification = {
				id,
				message: '',
				type: ToastType.INFO,
				duration: ToastDuration.STANDARD,
				isExiting: false,
				...options,
			};
			setToasts((prev) => [...prev, toast]);

			if (toast.duration !== ToastDuration.PERSISTENT) {
				setTimeout(() => dismissToast(id), toast.duration);
			}

			return id;
		},
		[dismissToast]
	);

	return (
		<ToastContext.Provider value={{ addToast, dismissToast }}>
			{children}
			<div className="wpo-otd-toast-container">
				{toasts.map((toast) => (
					<Toast
						key={toast.id}
						title={toast.title}
						message={toast.message}
						type={toast.type}
						isExiting={toast.isExiting}
						action={toast.action}
						onClose={() => dismissToast(toast.id)}
					/>
				))}
			</div>
		</ToastContext.Provider>
	);
};

export const useToast = (): ToastContextType => {
	const context = useContext(ToastContext);
	if (!context) {
		throw new Error('useToast must be used within a ToastProvider');
	}
	return context;
};
