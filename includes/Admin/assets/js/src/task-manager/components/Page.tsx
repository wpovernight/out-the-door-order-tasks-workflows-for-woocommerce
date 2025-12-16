import * as React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';

import Header from './Header';
import { CalendarView } from '../views/Calendar/CalendarView';
import { KanbanView } from '../views/Kanban/KanbanView';

export default function Page() {
	return (
		<div className="inner">
			<Header />
			{/* Use <Activity> component when WP React version updated to 19.2 */}
			<div className="views">
				<Routes>
					<Route
						path="/kanban"
						element={
							<div className="view kanban-view active">
								<KanbanView />
							</div>
						}
					/>
					<Route
						path="/calendar"
						element={
							<div className="view calendar-view active">
								<CalendarView />
							</div>
						}
					/>
					<Route path="/" element={<Navigate to="/kanban" replace />} />
				</Routes>
			</div>
		</div>
	);
}
