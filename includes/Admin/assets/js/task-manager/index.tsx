import React from 'react';
import {createRoot} from 'react-dom/client';
import {ViewProvider} from "./context/ViewContext";
import {TaskProvider} from "./context/TaskContext";
import Page from "./components/Page";

const container = document.getElementById('wpo-aom-task-manager-container');

if (container) {
	const root = createRoot(container);
	root.render(
		<React.StrictMode>
			<TaskProvider>
				<ViewProvider>
					<Page/>
				</ViewProvider>
			</TaskProvider>
		</React.StrictMode>
	);
}
