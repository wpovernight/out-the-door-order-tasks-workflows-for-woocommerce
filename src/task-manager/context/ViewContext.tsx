import React, {
	createContext,
	useContext,
	useEffect,
	useMemo,
	useState,
} from 'react';
import { useNavigate, useLocation } from 'react-router-dom';

// Define all available views.
export const AVAILABLE_VIEWS = ['kanban', 'calendar', 'archive'];

type View = (typeof AVAILABLE_VIEWS)[number];

interface ViewContextType {
	view: View;
	setView: (view: View) => void;
	searchQuery: string;
	setSearchQuery: (query: string) => void;
}

const ViewContext = createContext<ViewContextType | undefined>(undefined);

export const ViewProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const navigate = useNavigate();
	const location = useLocation();
	const [searchQuery, setSearchQuery] = useState('');

	// Extract view from the path segment after "/task-manager/" (e.g., "/task-manager/kanban" -> "kanban").
	const view = useMemo<View>(() => {
		const segments = location.pathname.split('/').filter(Boolean);
		const viewSegment = segments[1] || '';
		return AVAILABLE_VIEWS.includes(viewSegment)
			? (viewSegment as View)
			: 'kanban';
	}, [location.pathname]);

	// Reset search when switching views.
	useEffect(() => {
		setSearchQuery('');
	}, [view]);

	// Navigate to new view within the task-manager section.
	const setView = (newView: View) => {
		navigate(`/task-manager/${newView}`);
	};

	return (
		<ViewContext.Provider
			value={{ view, setView, searchQuery, setSearchQuery }}
		>
			{children}
		</ViewContext.Provider>
	);
};

/**
 * Custom hook to use the ViewContext.
 *
 * @return {ViewContextType} The current view and a function to set the view
 * @throws Will throw an error if used outside a ViewProvider
 */
export const useView = (): ViewContextType => {
	const context = useContext(ViewContext);
	if (!context) {
		throw new Error('useView must be used within a ViewProvider');
	}

	return context;
};
