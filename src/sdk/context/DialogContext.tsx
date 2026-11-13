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

/**
 * Options for a dialog with a third choice besides confirming and closing.
 */
export interface ChoiceOptions extends DialogOptions {
	alternativeText: string;
}

/**
 * What a choice dialog was answered with: its confirm button, its third
 * choice, or neither (cancel, Escape or a click outside).
 */
export type DialogChoice = 'confirm' | 'alternative' | 'cancel';

interface DialogState extends DialogOptions {
	variant: DialogVariant;
	alternativeText?: string;
}

interface DialogContextType {
	confirm: (options: DialogOptions) => Promise<boolean>;
	choose: (options: ChoiceOptions) => Promise<DialogChoice>;
	notice: (options: DialogOptions) => Promise<void>;
}

const DialogContext = createContext<DialogContextType | undefined>(undefined);

export const DialogProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const [state, setState] = useState<DialogState | null>(null);
	const resolverRef = useRef<((value: DialogChoice) => void) | null>(null);

	const open = useCallback(
		(
			variant: DialogVariant,
			options: DialogOptions & { alternativeText?: string }
		): Promise<DialogChoice> => {
			// If a dialog is already open, dismiss it as canceled so we
			// don't lose the previous resolver.
			if (resolverRef.current) {
				resolverRef.current('cancel');
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
		async (options: DialogOptions) =>
			'confirm' === (await open('confirm', options)),
		[open]
	);

	const choose = useCallback(
		(options: ChoiceOptions) => open('confirm', options),
		[open]
	);

	const notice = useCallback(
		async (options: DialogOptions) => {
			await open('notice', options);
		},
		[open]
	);

	const resolveWith = (value: DialogChoice) => {
		const resolver = resolverRef.current;
		resolverRef.current = null;
		setState(null);
		resolver?.(value);
	};

	return (
		<DialogContext.Provider value={{ confirm, choose, notice }}>
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
					alternativeText={state.alternativeText}
					onConfirm={() => resolveWith('confirm')}
					onAlternative={() => resolveWith('alternative')}
					onClose={() => resolveWith('cancel')}
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
/**
 * Asks with a third choice besides confirming and closing, answering which
 * was picked.
 */
export const useChoice = () => useDialogContext().choose;
export const useNotice = () => useDialogContext().notice;
