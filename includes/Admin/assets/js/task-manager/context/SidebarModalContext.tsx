import React, { createContext, useCallback, useContext, useState } from 'react';
import { SidebarModal } from '../components/SidebarModal';

type OpenOptions = {
	title?: string;
};

interface SidebarModalContextType {
	openSidebar: (content: React.ReactNode, options?: OpenOptions) => void;
	closeSidebar: () => void;
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

	const openSidebar = useCallback(
		(content: React.ReactNode, options?: OpenOptions) => {
			setIsOpen(true);
			setTitle(options?.title);
			setContent(content);
		},
		[]
	);

	const closeSidebar = useCallback(() => {
		setIsOpen(false);
		setTitle(undefined);
		setContent(null);
	}, []);

	return (
		<SidebarModalContext.Provider
			value={{
				openSidebar,
				closeSidebar,
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
