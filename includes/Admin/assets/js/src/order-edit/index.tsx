import React from 'react';
import { createRoot } from 'react-dom/client';
import { MetaBox } from './components/Metabox';
import { SidebarModalProvider } from '@shared/context/SidebarModalContext';
import { TaskProvider } from '@shared/context/TaskContext';
import { OrderTaskProvider } from './context/OrderTaskContext';

const container = document.getElementById('wpo-aom-order-meta-box-content');

if (container) {
	const orderId = window.WPO_AOM_OrderEdit?.orderId || 0;

	const root = createRoot(container);
	root.render(
		<TaskProvider>
			<OrderTaskProvider orderId={orderId}>
				<SidebarModalProvider>
					<MetaBox />
				</SidebarModalProvider>
			</OrderTaskProvider>
		</TaskProvider>
	);
}
