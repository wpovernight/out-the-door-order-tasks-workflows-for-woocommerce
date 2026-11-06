import React from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { ExampleRule } from '../exampleRules';
import { ProHint } from './ProHint';

type ChipType = 'trigger' | 'condition' | 'action' | 'more';

interface Chip {
	type: ChipType;
	label: string;
}

/**
 * The rule as a chain: its trigger, conditions and actions, then a chip
 * counting the actions not shown.
 *
 * @param rule The example rule.
 * @return The chips, in order.
 */
const chipsOf = (rule: ExampleRule): Chip[] => {
	const chips: Chip[] = [
		{ type: 'trigger', label: rule.trigger },
		...rule.conditions.map((label) => ({
			type: 'condition' as const,
			label,
		})),
		...rule.actions.map((label) => ({ type: 'action' as const, label })),
	];

	if (rule.moreActions > 0) {
		chips.push({
			type: 'more',
			label: sprintf(
				/* translators: %d: the number of actions not shown. */
				_n(
					'+ %d action',
					'+ %d actions',
					rule.moreActions,
					'out-the-door-order-tasks-workflows-for-woocommerce'
				),
				rule.moreActions
			),
		});
	}

	return chips;
};

/**
 * One example rule: a switched-off toggle, its title and its chain of chips.
 *
 * @param props
 * @param props.rule
 */
export const ExampleRuleRow = ({ rule }: { rule: ExampleRule }) => {
	const chips = chipsOf(rule);

	return (
		<li className="automations-rule">
			<ProHint className="automations-rule-toggle-hint">
				{(hintProps) => (
					<button
						type="button"
						role="switch"
						aria-checked="false"
						aria-label={sprintf(
							/* translators: %s: the rule's title. */
							__(
								'Switch on "%s"',
								'out-the-door-order-tasks-workflows-for-woocommerce'
							),
							rule.title
						)}
						className="automations-rule-toggle"
						{...hintProps}
					/>
				)}
			</ProHint>
			<div className="automations-rule-body">
				<h4 className="automations-rule-title">
					{rule.title}
					<span className="automations-example-badge">
						{__(
							'Example',
							'out-the-door-order-tasks-workflows-for-woocommerce'
						)}
					</span>
				</h4>
				<ol className="automations-rule-chain">
					{chips.map((chip, index) => (
						<li
							key={`${chip.type}-${index}`}
							className={`automations-chip automations-chip-${chip.type}`}
						>
							{index > 0 && chip.type !== 'more' && (
								<span
									className="automations-chip-arrow"
									aria-hidden="true"
								>
									→
								</span>
							)}
							<span className="automations-chip-label">
								{chip.label}
							</span>
						</li>
					))}
				</ol>
			</div>
		</li>
	);
};
