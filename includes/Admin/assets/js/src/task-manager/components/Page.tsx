import * as React from 'react';

import { useView } from '../context/ViewContext';
import Header from './Header';
import { CalendarView } from '../views/Calendar/CalendarView';
import { KanbanView } from '../views/Kanban/KanbanView';

export default function Page() {
	const { view } = useView();

	return (
		<div className="inner">
			<Header />
			{/* Use <Activity> component when WP React version updated to 19.2 */}
			<div className="views">
				<div
					className={`view kanban-view ${view === 'kanban' ? 'active' : ''}`}
				>
					<KanbanView />
				</div>
				<div
					className={`view calendar-view ${view === 'calendar' ? 'active' : ''}`}
				>
					<CalendarView />
				</div>
			</div>
		</div>
	);
}
