import React from 'react';
import { useOrderFulfillment } from '@orderEdit/context/OrderFulfillmentContext';
import { useOrderEditData } from '@orderEdit/hooks/useOrderEditData';
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
	const { i18n } = useOrderEditData();

	// Show loading state
	if (loadingStatus === 'loading') {
		return <FulfillmentCardSkeleton count={2} />;
	}

	// Show error state
	if (loadingStatus === 'error') {
		return (
			<ErrorState
				message={loadingError?.message || i18n.errorLoading}
				onRetry={refreshFulfillments}
			/>
		);
	}

	// Show empty state only after data is loaded
	if (loadingStatus === 'loaded' && fulfillments.length === 0) {
		return (
			<EmptyState
				icon="📦"
				message={i18n.fulfillments.noFulfillments}
				actionText={i18n.fulfillments.addFulfillment}
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
