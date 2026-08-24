import * as React from 'react';
import { applyFilters } from '@wordpress/hooks';
import { Routes, Route, Navigate } from 'react-router-dom';

import Header from './Header';
import { CalendarView } from '../views/Calendar/CalendarView';
import { KanbanView } from '../views/Kanban/KanbanView';
import { ArchiveView } from '@taskManager/views/Archive/ArchiveView';

export interface TaskManagerRoute {
	path: string;
	element: React.ReactNode;
}

const coreRoutes: TaskManagerRoute[] = [
	{ path: 'kanban', element: <KanbanView /> },
	{ path: 'calendar', element: <CalendarView /> },
	{ path: 'archive', element: <ArchiveView /> },
];

export default function Page() {
	const routes = applyFilters(
		'wpo_aom.task_manager_routes',
		coreRoutes
	) as TaskManagerRoute[];

	return (
		<div className="inner">
			<Header />
			{/* Use <Activity> component when WP React version updated to 19.2 */}
			<div className="views">
				<Routes>
					{routes.map((route) => (
						<Route
							key={route.path}
							path={route.path}
							element={
								<div
									className={`view ${route.path}-view active`}
								>
									{route.element}
								</div>
							}
						/>
					))}
					<Route path="" element={<Navigate to="kanban" replace />} />
				</Routes>
			</div>
		</div>
	);
}
