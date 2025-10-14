import React from "react";
import {AVAILABLE_VIEWS, useView} from "../context/ViewContext";

export default function Header() {
	const { view, setView } = useView();

	return (
		<div className="header">
			<nav className="views-filter">
				<ul>
					{AVAILABLE_VIEWS.map((availableView) => (
						<li key={availableView}
							className={view === availableView ? 'active' : ''}
							onClick={() => setView(availableView)}
						>
							{availableView}
						</li>
					))}
				</ul>
			</nav>
		</div>
	);
}
