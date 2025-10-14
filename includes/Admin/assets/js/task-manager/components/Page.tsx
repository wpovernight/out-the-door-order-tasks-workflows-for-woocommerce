import * as React from 'react';

import '../styles/kanban.css';
import {useView} from "../context/ViewContext";

import Header from "./Header";
import Calendar from "../views/Calendar/calendar";
import {KanbanView} from "../views/Kanban/KanbanView";

export default function Page() {
	const {view} = useView();

	return (
		<div className="inner">
			<Header/>

			{view === 'kanban' && <KanbanView/>}
			{view === 'calendar' && <Calendar/>}
		</div>
	);
}

