import React, { useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { AVAILABLE_VIEWS, useView } from '../context/ViewContext';
import { useTaskCreation, useOnClickOutside } from '@sdk';

const viewLabels: Record<string, string> = {
	kanban: __('Board', 'out-the-door-order-tasks-workflows-for-woocommerce'),
	calendar: __(
		'Calendar',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	),
	archive: __(
		'Archive',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	),
};

export default function Header() {
	const { view, setView, searchQuery, setSearchQuery } = useView();
	const { openCreateTaskModal } = useTaskCreation();

	const handleAddTask = () => {
		openCreateTaskModal({
			title: __(
				'Add Task',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			),
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
							{__(
								'Search',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
						</span>
					</label>
					<input
						type="search"
						id="header-search-input"
						placeholder={__(
							'Search',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)}
						value={searchQuery}
						onChange={(e) => setSearchQuery(e.target.value)}
					/>
				</div>
				<button
					type="button"
					className="wpo-button wpo-button-primary add-new-task"
					onClick={handleAddTask}
					title={__(
						'Add new task',
						'out-the-door-order-tasks-workflows-for-woocommerce'
					)}
				>
					<span className="screen-reader-text">
						{__(
							'Add new task',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)}
					</span>
				</button>
			</div>
		</div>
	);
}
