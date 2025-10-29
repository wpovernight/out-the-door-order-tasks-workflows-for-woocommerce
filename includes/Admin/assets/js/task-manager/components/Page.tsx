import * as React from 'react';

import { useView } from '../context/ViewContext';
import Header from './Header';
import Calendar from '../views/Calendar/calendar';
import { KanbanView } from '../views/Kanban/KanbanView';

export default function Page() {
	const { view } = useView();

	return (
		<div className="inner">
			<Header />
			{/* Use <Activity> component when WP React version updated to 19.2 */}
			<div className={`view ${view}-view`}>
				{view === 'kanban' && <KanbanView />}
				{view === 'calendar' && <Calendar />}
			</div>
		</div>
	);
}
