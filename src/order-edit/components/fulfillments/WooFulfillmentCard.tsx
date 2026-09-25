import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { WooFulfillment, getWooFulfillmentMeta, getColorStyle } from '@sdk';
import {
	getShippingMethodLabel,
	getTrackingInfo,
	formatDate,
} from '@orderEdit/components/utils/wooFulfillmentUtils';

interface WooFulfillmentCardProps {
	fulfillment: WooFulfillment;
	index: number;
}

export const WooFulfillmentCard: React.FC<WooFulfillmentCardProps> = ({
	fulfillment,
	index,
}) => {
	const meta = getWooFulfillmentMeta(fulfillment);
	const trackingInfo = getTrackingInfo(meta);

	return (
		<div className="fulfillment-card">
			<div className="fulfillment-card-header">
				<h4>
					{sprintf(
						/* translators: %d is the fulfillment number. */
						__('Fulfillment #%d', 'out-the-door-order-tasks-workflows-for-woocommerce'),
						index + 1
					)}
				</h4>
				<span
					className="wpo-aom-tag fulfillment-card-status"
					style={getColorStyle(
						fulfillment.status === 'fulfilled'
							? '#c6e1c6'
							: '#fbe5e5'
					)}
				>
					{fulfillment.status.charAt(0).toUpperCase() +
						fulfillment.status.slice(1)}
				</span>
			</div>
			<p className="fulfillment-card-items">
				{meta.items && meta.items.length > 0
					? meta.items
							.map((item) => `x${item.qty} ${item.name}`)
							.join(', ')
					: '-'}
			</p>

			<dl className="fulfillment-card-details">
				<dt className="fulfillment-card-provider">
					{getShippingMethodLabel(meta)}
				</dt>
				<dd>
					{trackingInfo && (
						<div className="fulfillment-card-tracking-container">
							<span className="fulfillment-card-tracking">
								{trackingInfo}
							</span>
							{meta.tracking_url && (
								<a
									href={meta.tracking_url}
									target="_blank"
									rel="noopener noreferrer"
									className="fulfillment-card-link"
									onClick={(e) => e.stopPropagation()}
								>
									<span className="screen-reader-text">
										{__(
											'Track Package',
											'out-the-door-order-tasks-workflows-for-woocommerce'
										)}
									</span>
								</a>
							)}
						</div>
					)}
				</dd>
				<dt>{__('Last Updated', 'out-the-door-order-tasks-workflows-for-woocommerce')}</dt>
				<dd>
					{fulfillment.status === 'fulfilled' &&
						meta.date_fulfilled &&
						formatDate(meta.date_fulfilled)}
					{fulfillment.status === 'unfulfilled' &&
						formatDate(fulfillment.date_updated)}
				</dd>
			</dl>
		</div>
	);
};
