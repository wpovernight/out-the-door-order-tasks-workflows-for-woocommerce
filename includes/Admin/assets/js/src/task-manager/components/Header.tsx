import React from 'react';
import { AVAILABLE_VIEWS, useView } from '../context/ViewContext';
import { useLocalized } from '@shared/hooks/useLocalized';

export default function Header() {
	const { view, setView } = useView();
	const localized = useLocalized();

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
								{localized.views[availableView]}
							</button>
						</li>
					))}
				</ul>
			</nav>
		</div>
	);
}
