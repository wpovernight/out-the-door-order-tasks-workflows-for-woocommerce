import React from 'react';
import Page from '@taskManager/components/Page';
import { ViewProvider } from '@taskManager/context/ViewContext';
import { SidebarModalProvider } from '@sdk';
import { __ } from '@wordpress/i18n';

export const TaskView = () => (
	<>
		<h2 className="screen-reader-text">
			{__(
				'Task Manager',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			)}
		</h2>
		<ViewProvider>
			<SidebarModalProvider>
				<Page />
			</SidebarModalProvider>
		</ViewProvider>
	</>
);
