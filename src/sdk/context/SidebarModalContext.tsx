import React, {
	createContext,
	useCallback,
	useContext,
	useRef,
	useState,
} from 'react';
import { SidebarModal } from '@sdk/components/SidebarModal';

type OpenOptions = {
	title?: string;
};

type BeforeCloseGuard = () => Promise<boolean>;

interface SidebarModalContextType {
	openSidebar: (content: React.ReactNode, options?: OpenOptions) => void;
	closeSidebar: () => void;
	setBeforeClose: (guard: BeforeCloseGuard | null) => void;
}

const SidebarModalContext = createContext<SidebarModalContextType | undefined>(
	undefined
);

export const SidebarModalProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const [isOpen, setIsOpen] = useState<boolean>(false);
	const [title, setTitle] = useState<string | undefined>();
	const [content, setContent] = useState<React.ReactNode>(null);
	const beforeCloseRef = useRef<BeforeCloseGuard | null>(null);

	const setBeforeClose = useCallback((guard: BeforeCloseGuard | null) => {
		beforeCloseRef.current = guard;
	}, []);

	const doClose = useCallback(() => {
		setIsOpen(false);
		setTitle(undefined);
		setContent(null);
		beforeCloseRef.current = null;
	}, []);

	const openSidebar = useCallback(
		(sidebarContent: React.ReactNode, options?: OpenOptions) => {
			setIsOpen(true);
			setTitle(options?.title);
			setContent(sidebarContent);
			beforeCloseRef.current = null;
		},
		[]
	);

	const closeSidebar = useCallback(async () => {
		if (beforeCloseRef.current) {
			const canClose = await beforeCloseRef.current();
			if (!canClose) {
				return;
			}
		}
		doClose();
	}, [doClose]);

	return (
		<SidebarModalContext.Provider
			value={{
				openSidebar,
				closeSidebar,
				setBeforeClose,
			}}
		>
			{children}
			<SidebarModal isOpen={isOpen} onClose={closeSidebar} title={title}>
				{content}
			</SidebarModal>
		</SidebarModalContext.Provider>
	);
};

export const useSidebarModal = (): SidebarModalContextType => {
	const context = useContext(SidebarModalContext);
	if (!context) {
		throw new Error(
			'useSidebarModal must be used within a SidebarModalProvider'
		);
	}
	return context;
};
