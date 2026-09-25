import React from 'react';
import { createRoot } from 'react-dom/client';
import { MetaBox } from './components/Metabox';
import {
	SidebarModalProvider,
	DialogProvider,
	ToastProvider,
	TaskProvider,
	StatusRoleProvider,
} from '@sdk';
import { OrderTaskProvider } from './context/OrderTaskContext';
import { OrderWooFulfillmentProvider } from './context/OrderWooFulfillmentContext';

const container = document.getElementById('wpo-otd-order-meta-box-content');

if (container) {
	const orderId = window.WPO_OTD_OrderEdit_MetaBox?.orderId || 0;

	const root = createRoot(container);
	root.render(
		<StatusRoleProvider>
			<TaskProvider>
				<OrderTaskProvider orderId={orderId}>
					<OrderWooFulfillmentProvider orderId={orderId}>
						<ToastProvider>
							<DialogProvider>
								<SidebarModalProvider>
									<MetaBox />
								</SidebarModalProvider>
							</DialogProvider>
						</ToastProvider>
					</OrderWooFulfillmentProvider>
				</OrderTaskProvider>
			</TaskProvider>
		</StatusRoleProvider>
	);
}
