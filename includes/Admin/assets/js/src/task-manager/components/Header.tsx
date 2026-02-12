import React from 'react';
import { __ } from '@wordpress/i18n';
import { AVAILABLE_VIEWS, useView } from '../context/ViewContext';

const viewLabels: Record<string, string> = {
	kanban: __( 'Kanban', 'wpo-aom' ),
	calendar: __( 'Calendar', 'wpo-aom' ),
};

export default function Header() {
	const { view, setView } = useView();

	return (
		<div className="header">
			<nav className="views-filter">
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
		</div>
	);
}
