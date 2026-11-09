import React, { useId } from 'react';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { getExampleRules } from './exampleRules';
import { ExampleRuleRow } from './components/ExampleRuleRow';
import { ProCard } from './components/ProCard';
import { ProHint } from './components/ProHint';

/**
 * A read-only preview of automation rules: three examples and the Pro card.
 * Every control is disabled and says it is available in Pro.
 */
export const AutomationsPreview = () => {
	/**
	 * Whether the preview shows the card about Pro's rules.
	 *
	 * @param {boolean} showUpsell True by default; return false to hide it.
	 */
	const showUpsell = applyFilters(
		'wpo_otd.automations_show_upsell',
		true
	) as boolean;

	const searchId = useId();
	const searchLabel = __(
		'Search',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	);

	return (
		<div className="automations-view">
			<div className="automations-header">
				<h3>
					{__(
						'Active Rules',
						'out-the-door-order-tasks-workflows-for-woocommerce'
					)}
				</h3>
				<div className="automations-header-actions">
					<ProHint className="automations-search">
						{(hintProps) => (
							<>
								<label htmlFor={searchId}>
									<span className="screen-reader-text">
										{searchLabel}
									</span>
								</label>
								<input
									type="search"
									id={searchId}
									readOnly
									placeholder={searchLabel}
									className="wpo-otd-search-input automations-search-input"
									{...hintProps}
								/>
							</>
						)}
					</ProHint>
					<ProHint>
						{(hintProps) => (
							<button
								type="button"
								className="wpo-button wpo-button-primary automations-new-rule"
								{...hintProps}
							>
								<span
									className="automations-icon automations-new-rule-icon"
									aria-hidden="true"
								/>
								{__(
									'New rule',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
							</button>
						)}
					</ProHint>
				</div>
			</div>
			<ul className="automations-rules">
				{getExampleRules().map((rule) => (
					<ExampleRuleRow key={rule.id} rule={rule} />
				))}
			</ul>
			<div className="automations-footer">
				<ProHint>
					{(hintProps) => (
						<button
							type="button"
							className="wpo-button wpo-button-secondary automations-add-rule"
							{...hintProps}
						>
							{__(
								'Add new rule',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							)}
						</button>
					)}
				</ProHint>
				<p>
					{__(
						'Rules do the repetitive part for you. Set one up once, and it happens every time — no one has to remember.',
						'out-the-door-order-tasks-workflows-for-woocommerce'
					)}
				</p>
			</div>
			{showUpsell !== false && <ProCard />}
		</div>
	);
};
