import React from 'react';
import { WooFulfillment, getWooFulfillmentMeta } from '@shared/types/wooFulfillment';
import {
	getShippingMethodLabel,
	getTrackingInfo,
	formatDate,
} from '@orderEdit/components/utils/wooFulfillmentUtils';
import { getColorStyle } from '@shared/utils/colorUtils';

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
				<h4>Fulfillment #{index + 1}</h4>
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
									<span className="screenReader">
										Track Package
									</span>
								</a>
							)}
						</div>
					)}
				</dd>
				{/*ToDo: i18n*/}
				<dt>Last Updated</dt>
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
