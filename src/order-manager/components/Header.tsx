import React from 'react';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { useTab } from '@orderManager/context/TabContext';

const coreTabLabels: Record<string, string> = {
	dashboard: __('Dashboard', 'advanced-order-manager-for-woocommerce'),
	'task-manager': __('Task Manager', 'advanced-order-manager-for-woocommerce'),
	'custom-order-status': __('Custom Order Status', 'advanced-order-manager-for-woocommerce'),
};

export default function Header() {
	const { tab, setTab, tabs } = useTab();

	const tabLabels = applyFilters(
		'wpo_aom.tab_labels',
		coreTabLabels
	) as Record<string, string>;

	return (
		<div className="header">
			<h1>Advanced Order Manager</h1>
			<nav className="tabs" id="main-tabs">
				<ul>
					{tabs.map((availableTab) => (
						<li
							key={availableTab}
							className={tab === availableTab ? 'active' : ''}
						>
							<button
								type="button"
								onClick={() => setTab(availableTab)}
								className="tab-button"
							>
								{tabLabels[availableTab] ?? availableTab}
							</button>
						</li>
					))}
				</ul>
			</nav>
		</div>
	);
}
