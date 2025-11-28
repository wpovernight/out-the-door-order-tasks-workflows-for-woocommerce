import React from 'react';
import { AVAILABLE_VIEWS, useView } from '../context/ViewContext';
import { useTaskManagerData } from '@taskManager/hooks/useTaskManagerData';

export default function Header() {
	const { view, setView } = useView();
	const { i18n } = useTaskManagerData();

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
								{i18n.views[availableView]}
							</button>
						</li>
					))}
				</ul>
			</nav>
		</div>
	);
}
