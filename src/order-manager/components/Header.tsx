import React from 'react';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { useTab } from '@orderManager/context/TabContext';

const coreTabLabels: Record<string, string> = {
	dashboard: __(
		'Dashboard',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	),
	'task-manager': __(
		'Task Manager',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	),
	'custom-order-status': __(
		'Custom Order Status',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	),
	automations: __(
		'Automations',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	),
};

export default function Header() {
	const { tab, setTab, tabs } = useTab();

	/**
	 * The tabs' labels, by tab slug. A label is any React node, so an add-on
	 * can put a count or a badge beside the text.
	 *
	 * @param {Record<string, React.ReactNode>} labels Core's labels, as text.
	 */
	const tabLabels = applyFilters(
		'wpo_otd.tab_labels',
		coreTabLabels
	) as Record<string, React.ReactNode>;

	return (
		<div className="header">
			<h1>Out the Door</h1>
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
