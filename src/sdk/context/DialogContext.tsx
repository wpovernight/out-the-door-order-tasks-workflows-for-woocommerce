import React, {
	createContext,
	useCallback,
	useContext,
	useRef,
	useState,
} from 'react';
import { Dialog, DialogAction, DialogVariant } from '@sdk/components/Dialog';

export interface DialogOptions {
	title: string;
	message: React.ReactNode;
	action?: DialogAction;
	confirmText?: string;
	cancelText?: string;
	invertActions?: boolean;
}

interface DialogState extends DialogOptions {
	variant: DialogVariant;
}

interface DialogContextType {
	confirm: (options: DialogOptions) => Promise<boolean>;
	notice: (options: DialogOptions) => Promise<void>;
}

const DialogContext = createContext<DialogContextType | undefined>(undefined);

export const DialogProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const [state, setState] = useState<DialogState | null>(null);
	const resolverRef = useRef<((value: boolean) => void) | null>(null);

	const open = useCallback(
		(variant: DialogVariant, options: DialogOptions): Promise<boolean> => {
			// If a dialog is already open, dismiss it as canceled so we
			// don't lose the previous resolver.
			if (resolverRef.current) {
				resolverRef.current(false);
				resolverRef.current = null;
			}
			return new Promise((resolve) => {
				resolverRef.current = resolve;
				setState({ ...options, variant });
			});
		},
		[]
	);

	const confirm = useCallback(
		(options: DialogOptions) => open('confirm', options),
		[open]
	);

	const notice = useCallback(
		async (options: DialogOptions) => {
			await open('notice', options);
		},
		[open]
	);

	const resolveWith = (value: boolean) => {
		const resolver = resolverRef.current;
		resolverRef.current = null;
		setState(null);
		resolver?.(value);
	};

	return (
		<DialogContext.Provider value={{ confirm, notice }}>
			{children}
			{state && (
				<Dialog
					title={state.title}
					message={state.message}
					variant={state.variant}
					action={state.action}
					confirmText={state.confirmText}
					cancelText={state.cancelText}
					invertActions={state.invertActions}
					onConfirm={() => resolveWith(true)}
					onClose={() => resolveWith(false)}
				/>
			)}
		</DialogContext.Provider>
	);
};

const useDialogContext = (): DialogContextType => {
	const context = useContext(DialogContext);
	if (!context) {
		throw new Error('Dialog hooks must be used within a DialogProvider');
	}
	return context;
};

export const useConfirm = () => useDialogContext().confirm;
export const useNotice = () => useDialogContext().notice;
