import React, {
	createContext,
	useCallback,
	useContext,
	useRef,
	useState,
} from 'react';
import { Toast } from '@shared/components/Toast';

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

export interface ToastOptions {
	title: string;
	message?: string;
	type?: ToastType;
	duration?: ToastDuration;
}

interface ToastNotification extends Required<ToastOptions> {
	id: number;
}

interface ToastContextType {
	addToast: (toast: ToastOptions) => void;
}

const ToastContext = createContext<ToastContextType | undefined>(undefined);

export const ToastProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const [toasts, setToasts] = useState<ToastNotification[]>([]);
	const nextId = useRef(0);

	const removeToast = useCallback((id: number) => {
		setToasts((prev) => prev.filter((toast) => toast.id !== id));
	}, []);

	const addToast = useCallback(
		(options: ToastOptions) => {
			const id = nextId.current++;
			const toast: ToastNotification = {
				id,
                message: '',
				type: ToastType.INFO,
				duration: ToastDuration.STANDARD,
				...options,
			};
			setToasts((prev) => [...prev, toast]);

			if (toast.duration !== ToastDuration.PERSISTENT) {
				setTimeout(() => removeToast(id), toast.duration);
			}
		},
		[removeToast]
	);

	return (
		<ToastContext.Provider value={{ addToast }}>
			{children}
			<div className="wpo-aom-toast-container">
				{toasts.map((toast) => (
					<Toast
						key={toast.id}
						title={toast.title}
						message={toast.message}
						type={toast.type}
						onClose={() => removeToast(toast.id)}
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
