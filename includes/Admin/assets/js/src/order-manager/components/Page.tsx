import React from 'react';
import { __ } from '@wordpress/i18n';
import { Navigate, Route, Routes } from 'react-router-dom';
import Header from '@orderManager/components/Header';
import { TaskView } from '@orderManager/views/TaskView';
import { useTab } from '@orderManager/context/TabContext';
import { DashboardView } from '@orderManager/views/Dashboard/DashboardView';
import { CustomOrderStatusView } from '@orderManager/views/CustomOrderStatus/CustomOrderStatusView';

export default function Page() {
	const { tab } = useTab();

	return (
		<div className="inner">
			<Header />
			<div className={`content ${tab}-tab`}>
				<Routes>
					<Route
						index
						element={<Navigate to="/dashboard" replace />}
					/>
					<Route path="/dashboard" element={<DashboardView />} />
					<Route path="/task-manager/*" element={<TaskView />} />
					<Route
						path="/custom-order-status"
						element={<CustomOrderStatusView />}
					/>
				</Routes>
			</div>
		</div>
	);
}
