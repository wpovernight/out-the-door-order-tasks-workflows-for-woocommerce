import React from 'react';
import { createRoot } from 'react-dom/client';
import { MetaBox } from './components/Metabox';
import { SidebarModalProvider } from '@sdk/context/SidebarModalContext';
import { DialogProvider } from '@sdk/context/DialogContext';
import { ToastProvider } from '@sdk/context/ToastContext';
import { TaskProvider } from '@sdk/context/TaskContext';
import { StatusRoleProvider } from '@sdk/context/StatusRoleContext';
import { OrderTaskProvider } from './context/OrderTaskContext';
import { OrderWooFulfillmentProvider } from './context/OrderWooFulfillmentContext';

const container = document.getElementById('wpo-aom-order-meta-box-content');

if (container) {
	const orderId = window.WPO_AOM_OrderEdit_MetaBox?.orderId || 0;

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
