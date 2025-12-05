import React from 'react';
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

	const handleAddFulfillment = () => {
		// ToDo: Open fulfillment creation modal
	};

	// Show loading state
	if (loadingStatus === 'loading') {
		return <FulfillmentCardSkeleton count={2} />;
	}

	// Show error state
	if (loadingStatus === 'error') {
		return (
			<ErrorState
				message={
					loadingError?.message ||
					'Failed to load fulfillments. Please try again.' // ToDo: i18n
				}
				onRetry={refreshFulfillments}
			/>
		);
	}

	// Show empty state only after data is loaded
	if (loadingStatus === 'loaded' && fulfillments.length === 0) {
		return (
			<EmptyState
				icon="📦"
				message="No fulfillments yet." // ToDo: i18n
				actionText="Add Fulfillment" // ToDo: i18n
				onAction={handleAddFulfillment}
				actionButtonProps={
					{
						'data-order-id': orderId,
						className: 'fulfillments-trigger',
					} as React.ButtonHTMLAttributes<HTMLButtonElement>
				}
			/>
		);
	}

	return (
		<div className="fulfillments-list-container">
			<h4 className="screenReader">Fulfillments</h4>
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
