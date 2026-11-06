import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { PRO_UPSELL_URL } from '../constants';

/**
 * The card telling people that Pro builds their own rules.
 */
export const ProCard = () => (
	<section className="automations-pro-card" aria-labelledby="otd-pro-rules">
		<h3 id="otd-pro-rules" className="automations-pro-card-title">
			<svg
				className="automations-pro-card-lock"
				width="20"
				height="20"
				viewBox="0 0 24 24"
				fill="none"
				stroke="currentColor"
				strokeWidth="2"
				strokeLinecap="round"
				strokeLinejoin="round"
				aria-hidden="true"
				focusable="false"
			>
				<rect x="4" y="11" width="16" height="10" rx="2" />
				<path d="M8 11V7a4 4 0 0 1 8 0v4" />
				<circle cx="12" cy="16" r="1" />
			</svg>
			{__('Rules', 'out-the-door-order-tasks-workflows-for-woocommerce')}
			<span className="automations-pro-badge">
				{__(
					'Pro',
					'out-the-door-order-tasks-workflows-for-woocommerce'
				)}
			</span>
		</h3>
		<p className="automations-pro-card-text">
			{__(
				'Build your own rules from your orders, products, customers and tasks. Set them up once and they run on their own.',
				'out-the-door-order-tasks-workflows-for-woocommerce'
			)}
		</p>
		<a
			className="automations-pro-card-link"
			href={PRO_UPSELL_URL}
			target="_blank"
			rel="noopener noreferrer"
		>
			{sprintf(
				/* translators: %s: Pro's price, e.g. "$99/year". */
				__(
					"See what's in Pro - %s",
					'out-the-door-order-tasks-workflows-for-woocommerce'
				),
				__(
					'$99/year',
					'out-the-door-order-tasks-workflows-for-woocommerce'
				)
			)}
			<svg
				className="automations-pro-card-link-arrow"
				width="16"
				height="16"
				viewBox="0 0 24 24"
				fill="none"
				stroke="currentColor"
				strokeWidth="2"
				strokeLinecap="round"
				strokeLinejoin="round"
				aria-hidden="true"
				focusable="false"
			>
				<path d="M7 17 17 7M8 7h9v9" />
			</svg>
			<span className="screen-reader-text">
				{__(
					'(opens in a new tab)',
					'out-the-door-order-tasks-workflows-for-woocommerce'
				)}
			</span>
		</a>
	</section>
);
