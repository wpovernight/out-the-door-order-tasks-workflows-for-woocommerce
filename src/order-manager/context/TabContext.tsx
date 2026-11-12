import React, { createContext, useContext, useMemo } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { applyFilters } from '@wordpress/hooks';
import { confirmLeave, hasLeaveGuard } from '@sdk';

export const CORE_TABS = [
	'dashboard',
	'task-manager',
	'custom-order-status',
	'automations',
];

type Tab = string;

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

	const tabs = applyFilters('wpo_otd.tabs', CORE_TABS) as Tab[];

	// Extract tab from the first path segment (e.g., "/task-manager/kanban" -> "task-manager").
	const tab = useMemo<Tab>(() => {
		const firstSegment =
			location.pathname.split('/').filter(Boolean)[0] || '';
		return tabs.includes(firstSegment) ? firstSegment : 'dashboard';
	}, [location.pathname, tabs]);

	// Navigate to new tab instead of setting state, once a view guarding
	// against being left (useLeaveGuard) lets it go.
	const setTab = (newTab: Tab) => {
		const go = () => navigate(`/${newTab}`);

		if (!hasLeaveGuard()) {
			go();
			return;
		}

		void confirmLeave().then((mayLeave) => {
			if (mayLeave) {
				go();
			}
		});
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
