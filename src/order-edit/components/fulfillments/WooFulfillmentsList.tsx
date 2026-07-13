import React from 'react';
import { __ } from '@wordpress/i18n';
import { useOrderWooFulfillment } from '@orderEdit/context/OrderWooFulfillmentContext';
import { WooFulfillmentCard } from './WooFulfillmentCard';
import { WooFulfillmentCardSkeleton } from './WooFulfillmentCardSkeleton';
import { EmptyState, ErrorState } from '@shared/components/LoadingSkeleton';
import { AsyncLoaderStatus } from '@shared/hooks/useAsyncLoader';

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
						'advanced-order-manager'
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
				message={__('No fulfillments yet.', 'advanced-order-manager')}
				actionText={__('Add Fulfillment', 'advanced-order-manager')}
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
				{__('Fulfillments', 'advanced-order-manager')}
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
