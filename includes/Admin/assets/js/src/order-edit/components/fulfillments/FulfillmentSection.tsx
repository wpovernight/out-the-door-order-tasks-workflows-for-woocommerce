import React from 'react';
import Header from '@orderEdit/components/fulfillments/Header';
import FulfillmentsList from '@orderEdit/components/fulfillments/FulfillmentsList';
import { useAsyncLoader } from '@shared/hooks/useAsyncLoader';
import { useOrderFulfillment } from '@orderEdit/context/OrderFulfillmentContext';

const FulfillmentSection: React.FC = () => {
	const { loadFulfillments } = useOrderFulfillment();

	// loadFulfillments now handles loading the order internally
	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await loadFulfillments();
	}, [loadFulfillments]);

	return (
		<div className="wpo-aom-metabox-section" id="order-fulfillments">
			<Header />
			<FulfillmentsList
				loadingStatus={loadingStatus}
				loadingError={loadingError}
			/>
		</div>
	);
};

export default FulfillmentSection;
