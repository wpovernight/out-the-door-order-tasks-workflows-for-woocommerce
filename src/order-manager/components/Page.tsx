import React from 'react';
import { applyFilters } from '@wordpress/hooks';
import { Navigate, Route, Routes } from 'react-router-dom';
import Header from '@orderManager/components/Header';
import { TaskView } from '@orderManager/views/TaskView';
import { useTab } from '@orderManager/context/TabContext';
import { DashboardView } from '@orderManager/views/Dashboard/DashboardView';
import { CustomOrderStatusView } from '@orderManager/views/CustomOrderStatus/CustomOrderStatusView';

export interface OtdRoute {
	path: string;
	element: React.ReactNode;
}

const coreRoutes: OtdRoute[] = [
	{ path: '/dashboard', element: <DashboardView /> },
	{ path: '/task-manager/*', element: <TaskView /> },
	{ path: '/custom-order-status', element: <CustomOrderStatusView /> },
];

export default function Page() {
	const { tab } = useTab();

	const routes = applyFilters('wpo_otd.routes', coreRoutes) as OtdRoute[];

	return (
		<div className="inner">
			<Header />
			<div className={`content ${tab}-tab`}>
				<Routes>
					<Route
						index
						element={<Navigate to="/dashboard" replace />}
					/>
					{routes.map((route) => (
						<Route
							key={route.path}
							path={route.path}
							element={route.element}
						/>
					))}
				</Routes>
			</div>
		</div>
	);
}
