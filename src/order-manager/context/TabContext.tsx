import React, { createContext, useContext, useMemo } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { applyFilters } from '@wordpress/hooks';

export const CORE_TABS = ['dashboard', 'task-manager', 'custom-order-status'];

type Tab = string;

/**
 * The list of tabs to render, after add-on plugins (e.g. Pro) have had a
 * chance to extend it.
 *
 * @param {string[]} tabs The slugs of the core tabs.
 * @return {string[]} The (possibly extended) list of tab slugs.
 */
export const getAvailableTabs = (): Tab[] =>
	applyFilters('wpo_aom.tabs', CORE_TABS) as Tab[];

interface TabContextType {
	tab: Tab;
	setTab: (tab: Tab) => void;
	tabs: Tab[];
}

const TabContext = createContext<TabContextType | undefined>(undefined);

export const TabProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const navigate = useNavigate();
	const location = useLocation();

	// Resolved once per render so late-registered filters still take effect.
	const tabs = useMemo<Tab[]>(() => getAvailableTabs(), []);

	// Extract tab from the first path segment (e.g., "/task-manager/kanban" -> "task-manager").
	const tab = useMemo<Tab>(() => {
		const firstSegment =
			location.pathname.split('/').filter(Boolean)[0] || '';
		return tabs.includes(firstSegment) ? firstSegment : 'dashboard';
	}, [location.pathname, tabs]);

	// Navigate to new tab instead of setting state.
	const setTab = (newTab: Tab) => {
		navigate(`/${newTab}`);
	};

	return (
		<TabContext.Provider value={{ tab, setTab, tabs }}>
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
