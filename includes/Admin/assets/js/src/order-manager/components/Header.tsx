import React from 'react';
import { __ } from '@wordpress/i18n';
import { AVAILABLE_TABS, useTab } from '@orderManager/context/TabContext';

const tabLabels: Record<string, string> = {
	dashboard: __('Dashboard', 'wpo-advanced-order-manager'),
	'task-manager': __('Task Manager', 'wpo-advanced-order-manager'),
	'custom-order-status': __('Custom Order Status', 'wpo-advanced-order-manager'),
};

export default function Header() {
	const { tab, setTab } = useTab();

	return (
		<div className="header">
			<h1>Advanced Order Manager</h1>
			<nav className="tabs" id="main-tabs">
				<ul>
					{AVAILABLE_TABS.map((availableTab) => (
						<li
							key={availableTab}
							className={tab === availableTab ? 'active' : ''}
						>
							<button
								type="button"
								onClick={() => setTab(availableTab)}
								className="tab-button"
							>
								{tabLabels[availableTab]}
							</button>
						</li>
					))}
				</ul>
			</nav>
		</div>
	);
}
