import React from 'react';
import { createRoot } from 'react-dom/client';
import { MetaBox } from './components/Metabox';
import { SidebarModalProvider } from '@shared/context/SidebarModalContext';
import { TaskProvider } from '@shared/context/TaskContext';
import { OrderTaskProvider } from './context/OrderTaskContext';
import { OrderFulfillmentProvider } from './context/OrderFulfillmentContext';

const container = document.getElementById('wpo-aom-order-meta-box-content');

if (container) {
	const orderId = window.WPO_AOM_OrderEdit_MetaBox?.orderId || 0;

	const root = createRoot(container);
	root.render(
		<TaskProvider>
			<OrderTaskProvider orderId={orderId}>
				<OrderFulfillmentProvider orderId={orderId}>
					<SidebarModalProvider>
						<MetaBox />
					</SidebarModalProvider>
				</OrderFulfillmentProvider>
			</OrderTaskProvider>
		</TaskProvider>
	);
}
