import React from 'react';
import Page from '@taskManager/components/Page';
import { ViewProvider } from '@taskManager/context/ViewContext';
import { SidebarModalProvider } from '@sdk/context/SidebarModalContext';
import { __ } from '@wordpress/i18n';

export const TaskView = () => (
	<>
		<h2 className="screen-reader-text">
			{__('Task Manager', 'advanced-order-manager')}
		</h2>
		<ViewProvider>
			<SidebarModalProvider>
				<Page />
			</SidebarModalProvider>
		</ViewProvider>
	</>
);
