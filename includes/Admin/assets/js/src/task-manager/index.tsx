import React from 'react';
import { createRoot } from 'react-dom/client';
import { ViewProvider } from './context/ViewContext';
import { TaskProvider } from './context/TaskContext';
import Page from './components/Page';
import { SidebarModalProvider } from '@shared/context/SidebarModalContext';

const container = document.getElementById('wpo-aom-task-manager-container');

if (container) {
	const root = createRoot(container);
	root.render(
		// <React.StrictMode>
		<TaskProvider>
			<ViewProvider>
				<SidebarModalProvider>
					<Page />
				</SidebarModalProvider>
			</ViewProvider>
		</TaskProvider>
		// </React.StrictMode>
	);
}
