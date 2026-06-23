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
	const [isAddMenuOpen, setIsAddMenuOpen] = useState(false);
	const addMenuRef = useRef<HTMLDivElement>(null);
	const { view, setView, searchQuery, setSearchQuery } = useView();
	const { openCreateTaskModal } = useTaskCreation();

	useOnClickOutside(addMenuRef, () => setIsAddMenuOpen(false));

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
						onClick={() => setIsAddMenuOpen((prev) => !prev)}
					>
						<span className="screen-reader-text">
							{__('Add new task', 'wpo-advanced-order-manager')}
						</span>
					</button>
					<ul
						className={`wpo-action-menu add-menu ${
							isAddMenuOpen ? 'open' : ''
						}`}
					>
						<li>
							<button
								type="button"
								role="menuitem"
								className="wpo-button add-task-menu-item"
								onClick={() => {
									openCreateTaskModal({
										title: __(
											'Add new task',
											'wpo-advanced-order-manager'
										),
									});
									setIsAddMenuOpen(false);
								}}
							>
								{__('New task', 'wpo-advanced-order-manager')}
							</button>
						</li>
						<li>
							<button
								type="button"
								className="wpo-button add-column-menu-item"
								onClick={() => {}}
							>
								{__('New column', 'wpo-advanced-order-manager')}
							</button>
						</li>
					</ul>
				</div>
			</div>
		</div>
	);
}
