import React from 'react';
import { createRoot } from 'react-dom/client';
import { HashRouter } from 'react-router-dom';
import { TabProvider } from '@orderManager/context/TabContext';
import Page from '@orderManager/components/Page';
import { TaskProvider } from '@shared/context/TaskContext';
import { StatusRoleProvider } from '@shared/context/StatusRoleContext';
import { DialogProvider } from '@shared/context/DialogContext';
import { ToastProvider } from '@shared/context/ToastContext';

const container = document.getElementById('wpo-aom-order-manager');

if (container) {
	const root = createRoot(container);
	root.render(
		<HashRouter>
			<TabProvider>
				{/* Wrap the entire application with TaskProvider to prevent unmounting of TaskView.
				Since we are using React Router, when we navigate away from the TaskView route, the
				component will unmount and lose its state. By placing TaskProvider at a higher level
				 in the component tree, we ensure that the state is preserved even when navigating
				 between different views.
				 This can be improved by using the <Activity> component when it is supported by WordPress.
				 We also need to use task data in the dashboard view, so we need to keep the TaskProvider
				 at this level for now.
				  */}
				<StatusRoleProvider>
					<TaskProvider>
						<ToastProvider>
							<DialogProvider>
								<Page />
							</DialogProvider>
						</ToastProvider>
					</TaskProvider>
				</StatusRoleProvider>
			</TabProvider>
		</HashRouter>
	);
}
