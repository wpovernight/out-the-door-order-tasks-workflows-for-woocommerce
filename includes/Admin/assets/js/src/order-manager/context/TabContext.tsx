import React, { createContext, useContext, useMemo } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';

export const AVAILABLE_TABS = [
	'dashboard',
	'task-manager',
	'custom-order-status',
];

type Tab = (typeof AVAILABLE_TABS)[number];

interface TabContextType {
	tab: Tab;
	setTab: (tab: Tab) => void;
}

const TabContext = createContext<TabContextType | undefined>(undefined);

export const TabProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const navigate = useNavigate();
	const location = useLocation();

	// Extract tab from the first path segment (e.g., "/task-manager/kanban" -> "task-manager").
	const tab = useMemo<Tab>(() => {
		const firstSegment =
			location.pathname.split('/').filter(Boolean)[0] || '';
		return AVAILABLE_TABS.includes(firstSegment)
			? (firstSegment as Tab)
			: 'dashboard';
	}, [location.pathname]);

	// Navigate to new tab instead of setting state.
	const setTab = (newTab: Tab) => {
		navigate(`/${newTab}`);
	};

	return (
		<TabContext.Provider value={{ tab, setTab }}>
			{children}
		</TabContext.Provider>
	);
};

/**
 * Custom hook to use the TabContext.
 *
 * @return {TabContextType} The current tab and a function to set the tab
 * @throws Will throw an error if used outside a TabProvider
 */
export const useTab = (): TabContextType => {
	const context = useContext(TabContext);
	if (!context) {
		throw new Error('useTab must be used within a TabProvider');
	}

	return context;
};
