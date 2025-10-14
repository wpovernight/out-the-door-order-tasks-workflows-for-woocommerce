import React, {createContext, use, useContext, useState} from "react";

// Define all available views.
export const AVAILABLE_VIEWS = ['kanban', 'calendar'];

type View = typeof AVAILABLE_VIEWS[number];

interface ViewContextType {
	view: View;
	setView: (view: View) => void;
}

const ViewContext = createContext<ViewContextType | undefined>(undefined);

/**
 * Provider component to wrap the part of the app that needs access to the view state.
 *
 * @param children
 * @constructor
 */
export const ViewProvider: React.FC<{ children: React.ReactNode }> = ({children}) => {
	const [view, setView] = useState<View>('kanban');

	return (
		<ViewContext.Provider value={{view, setView}}>
			{children}
		</ViewContext.Provider>
	);
}

/**
 * Custom hook to use the ViewContext.
 *
 * @returns {ViewContextType} The current view and a function to set the view
 * @throws Will throw an error if used outside a ViewProvider
 */
export const useView = (): ViewContextType => {
	const context = useContext(ViewContext);
	if (!context) {
		throw new Error('useView must be used within a ViewProvider');
	}

	return context;
}
