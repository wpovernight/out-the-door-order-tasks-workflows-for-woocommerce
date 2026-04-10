import React from 'react';
import { createRoot } from 'react-dom/client';
import { MetaBox } from './components/Metabox';
import { SidebarModalProvider } from '@shared/context/SidebarModalContext';
import { DialogProvider } from '@shared/context/DialogContext';
import { TaskProvider } from '@shared/context/TaskContext';
import { OrderTaskProvider } from './context/OrderTaskContext';
import { OrderWooFulfillmentProvider } from './context/OrderWooFulfillmentContext';

const container = document.getElementById('wpo-aom-order-meta-box-content');

if (container) {
	const orderId = window.WPO_AOM_OrderEdit_MetaBox?.orderId || 0;

	const root = createRoot(container);
	root.render(
		<TaskProvider>
			<OrderTaskProvider orderId={orderId}>
				<OrderWooFulfillmentProvider orderId={orderId}>
					<DialogProvider>
						<SidebarModalProvider>
							<MetaBox />
						</SidebarModalProvider>
					</DialogProvider>
				</OrderWooFulfillmentProvider>
			</OrderTaskProvider>
		</TaskProvider>
	);
}
