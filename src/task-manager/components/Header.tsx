import React, { useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { AVAILABLE_VIEWS, useView } from '../context/ViewContext';
import { useTaskCreation } from '@shared/hooks/useTaskFormModal';
import { useOnClickOutside } from '@shared/hooks/useOnClickOutside';

const viewLabels: Record<string, string> = {
	kanban: __('Board', 'wpo-advanced-order-manager'),
	calendar: __('Calendar', 'wpo-advanced-order-manager'),
	archive: __('Archive', 'wpo-advanced-order-manager'),
};

export default function Header() {
	const addMenuRef = useRef<HTMLDivElement>(null);
	const { view, setView, searchQuery, setSearchQuery } = useView();
	const { openCreateTaskModal } = useTaskCreation();

	const handleAddTask = () => {
		openCreateTaskModal({
			title: __('Add Task', 'wpo-advanced-order-manager'),
			initialValues: {
				dueDate: new Date().toISOString().split('T')[0],
			},
		});
	};

	return (
		<div className="header">
			<nav className="tabs" id="view-tabs">
				<ul>
					{AVAILABLE_VIEWS.map((availableView) => (
						<li
							key={availableView}
							className={view === availableView ? 'active' : ''}
						>
							<button
								type="button"
								onClick={() => setView(availableView)}
								className="view-button"
							>
								{viewLabels[availableView]}
							</button>
						</li>
					))}
				</ul>
			</nav>

			<div className="header-actions">
				<div className="header-search">
					<label htmlFor="header-search-input">
						<span className="screen-reader-text">
							{__('Search', 'wpo-advanced-order-manager')}
						</span>
					</label>
					<input
						type="search"
						id="header-search-input"
						placeholder={__('Search', 'wpo-advanced-order-manager')}
						value={searchQuery}
						onChange={(e) => setSearchQuery(e.target.value)}
					/>
				</div>
				<div ref={addMenuRef} className="add-menu-container">
					<button
						type="button"
						className="wpo-button wpo-button-primary add-menu-button"
						onClick={handleAddTask}
					>
						<span className="screen-reader-text">
							{__('Add new task', 'wpo-advanced-order-manager')}
						</span>
					</button>
				</div>
			</div>
		</div>
	);
}
