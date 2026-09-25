import React from 'react';
import { applyFilters } from '@wordpress/hooks';
import { useViewTasks } from '../context/ViewTaskContext';

export const KanbanToolbar: React.FC = () => {
	const { boardControls, setBoardControls } = useViewTasks();

	const content = applyFilters('wpo_otd.kanban_toolbar', null, {
		controls: boardControls,
		setControls: setBoardControls,
	}) as React.ReactNode;

	if (!content) {
		return null;
	}

	return <div className="kanban-toolbar">{content}</div>;
};
