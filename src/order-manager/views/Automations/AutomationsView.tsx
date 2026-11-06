import React from 'react';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { AutomationsPreview } from './AutomationsPreview';

/**
 * The Automations tab, under the `/automations/*` route.
 */
export const AutomationsView = () => {
	/**
	 * Replaces the Automations tab's content: an add-on returns its own screen,
	 * which may route under `/automations/`, in place of core's preview.
	 *
	 * @param {React.ReactNode} view Null for core's preview.
	 */
	const view = applyFilters(
		'wpo_otd.automations_view',
		null
	) as React.ReactNode;

	return (
		<>
			<h2 className="screen-reader-text">
				{__(
					'Automations',
					'out-the-door-order-tasks-workflows-for-woocommerce'
				)}
			</h2>
			{view ?? <AutomationsPreview />}
		</>
	);
};
