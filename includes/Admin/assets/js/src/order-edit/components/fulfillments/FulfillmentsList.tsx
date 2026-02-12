import React from 'react';
import { __ } from '@wordpress/i18n';
import { useOrderFulfillment } from '@orderEdit/context/OrderFulfillmentContext';
import { FulfillmentCard } from './FulfillmentCard';
import { FulfillmentCardSkeleton } from './FulfillmentCardSkeleton';
import { EmptyState, ErrorState } from '@shared/components/LoadingSkeleton';
import { AsyncLoaderStatus } from '@shared/hooks/useAsyncLoader';

interface FulfillmentsListProps {
	loadingStatus: AsyncLoaderStatus;
	loadingError: Error | null;
}

const FulfillmentsList: React.FC<FulfillmentsListProps> = ({
	loadingStatus,
	loadingError,
}) => {
	const { fulfillments, refreshFulfillments, orderId } =
		useOrderFulfillment();

	// Show loading state
	if (loadingStatus === 'loading') {
		return <FulfillmentCardSkeleton count={2} />;
	}

	// Show error state
	if (loadingStatus === 'error') {
		return (
			<ErrorState
				message={loadingError?.message || __( 'Error loading data. Please try again.', 'wpo-aom' )}
				onRetry={refreshFulfillments}
			/>
		);
	}

	// Show empty state only after data is loaded
	if (loadingStatus === 'loaded' && fulfillments.length === 0) {
		return (
			<EmptyState
				icon="📦"
				message={__( 'No fulfillments yet.', 'wpo-aom' )}
				actionText={__( 'Add Fulfillment', 'wpo-aom' )}
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
			<h4 className="screenReader">{__( 'Fulfillments', 'wpo-aom' )}</h4>
			<ul className="fulfillments-list">
				{fulfillments.map((fulfillment, index) => (
					<li key={fulfillment.id}>
						<FulfillmentCard
							fulfillment={fulfillment}
							index={index}
						/>
					</li>
				))}
			</ul>
		</div>
	);
};

export default FulfillmentsList;
