import React, { createContext, useContext, useMemo } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';

// Define all available views.
export const AVAILABLE_VIEWS = ['kanban', 'calendar'];

type View = (typeof AVAILABLE_VIEWS)[number];

interface ViewContextType {
	view: View;
	setView: (view: View) => void;
}

const ViewContext = createContext<ViewContextType | undefined>(undefined);

export const ViewProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	const navigate = useNavigate();
	const location = useLocation();

	// Extract view from URL hash path (e.g., "/kanban" -> "kanban")
	const view = useMemo<View>(() => {
		const pathView = location.pathname.slice(1); // Remove leading "/"
		return AVAILABLE_VIEWS.includes(pathView)
			? (pathView as View)
			: 'kanban';
	}, [location.pathname]);

	// Navigate to new view instead of setting state.
	const setView = (newView: View) => {
		navigate(`/${newView}`);
	};

	return (
		<ViewContext.Provider value={{ view, setView }}>
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
