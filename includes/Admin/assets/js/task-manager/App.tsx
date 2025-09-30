import * as React from 'react';
import KanbanBoard from './components/kanban/Board';
import './styles/kanban.css';


function App() {
	return (
		<div className="container">
			<div className="header">
				<h1>Task Management</h1>
				<div className="views"></div>
			</div>
			<KanbanBoard />
		</div>
	);
}

export default App;
