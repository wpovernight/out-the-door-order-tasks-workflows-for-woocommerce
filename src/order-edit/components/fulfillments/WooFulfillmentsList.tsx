import React from 'react';
import { __ } from '@wordpress/i18n';
import { useOrderWooFulfillment } from '@orderEdit/context/OrderWooFulfillmentContext';
import { WooFulfillmentCard } from './WooFulfillmentCard';
import { WooFulfillmentCardSkeleton } from './WooFulfillmentCardSkeleton';
import { EmptyState, ErrorState, AsyncLoaderStatus } from '@sdk';

interface WooFulfillmentsListProps {
	loadingStatus: AsyncLoaderStatus;
	loadingError: Error | null;
}

const WooFulfillmentsList: React.FC<WooFulfillmentsListProps> = ({
	loadingStatus,
	loadingError,
}) => {
	const { fulfillments, refreshFulfillments, orderId } =
		useOrderWooFulfillment();

	// Show loading state
	if (loadingStatus === 'loading') {
		return <WooFulfillmentCardSkeleton count={2} />;
	}

	// Show error state
	if (loadingStatus === 'error') {
		return (
			<ErrorState
				message={
					loadingError?.message ||
					__(
						'Error loading data. Please try again.',
						'out-the-door-order-tasks-workflows-for-woocommerce'
					)
				}
				onRetry={refreshFulfillments}
			/>
		);
	}

	// Show empty state only after data is loaded
	if (loadingStatus === 'loaded' && fulfillments.length === 0) {
		return (
			<EmptyState
				icon="box"
				message={__('No fulfillments yet.', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				actionText={__('Add Fulfillment', 'out-the-door-order-tasks-workflows-for-woocommerce')}
				actionButtonProps={
					{
						'data-order-id': orderId,
						className: 'fulfillments-trigger', // This class is used to bind the click event
					} as React.ButtonHTMLAttributes<HTMLButtonElement>
				}
			/>
		);
	}

	return (
		<div className="fulfillments-list-container">
			<h4 className="screen-reader-text">
				{__('Fulfillments', 'out-the-door-order-tasks-workflows-for-woocommerce')}
			</h4>
			<ul className="fulfillments-list">
				{fulfillments.map((fulfillment, index) => (
					<li key={fulfillment.id}>
						<WooFulfillmentCard
							fulfillment={fulfillment}
							index={index}
						/>
					</li>
				))}
			</ul>
		</div>
	);
};

export default WooFulfillmentsList;
