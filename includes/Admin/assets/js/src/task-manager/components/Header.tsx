import React from 'react';
import { __ } from '@wordpress/i18n';
import { AVAILABLE_VIEWS, useView } from '../context/ViewContext';
import { useTaskCreation } from '@shared/hooks/useTaskFormModal';

const viewLabels: Record<string, string> = {
	kanban: __('Board', 'wpo-aom'),
	calendar: __('Calendar', 'wpo-aom'),
	archive: __('Archive', 'wpo-aom'),
};

export default function Header() {
	const { view, setView, searchQuery, setSearchQuery } = useView();
	const { openCreateTaskModal } = useTaskCreation();

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
						<span className="screenReader">
							{__('Search', 'wpo-aom')}
						</span>
					</label>
					<input
						type="search"
						id="header-search-input"
						placeholder={__('Search', 'wpo-aom')}
						value={searchQuery}
						onChange={(e) => setSearchQuery(e.target.value)}
					/>
				</div>
				<button
					type="button"
					className="wpo-button wpo-button-primary add-task"
					onClick={() =>
						openCreateTaskModal({
							title: __('Add new task', 'wpo-aom'),
						})
					}
				>
					{__('Add new task', 'wpo-aom')}
				</button>
			</div>
		</div>
	);
}
