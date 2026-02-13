import React from 'react';
import { __ } from '@wordpress/i18n';
import { AVAILABLE_TABS, useTab } from '@orderManager/context/TabContext';

const tabLabels: Record<string, string> = {
	dashboard: __('Dashboard', 'wpo-aom'),
	'task-manager': __('Task Manager', 'wpo-aom'),
	'custom-order-status': __('Custom Order Status', 'wpo-aom'),
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
