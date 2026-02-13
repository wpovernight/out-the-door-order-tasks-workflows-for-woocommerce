import React from 'react';
import Page from '@taskManager/components/Page';
import { ViewProvider } from '@taskManager/context/ViewContext';
import { SidebarModalProvider } from '@shared/context/SidebarModalContext';

export const TaskView = () => (
	<ViewProvider>
		<SidebarModalProvider>
			<Page />
		</SidebarModalProvider>
	</ViewProvider>
);
