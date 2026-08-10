import React from 'react';
import Header from '@orderEdit/components/fulfillments/Header';
import WooFulfillmentsList from '@orderEdit/components/fulfillments/WooFulfillmentsList';
import { useAsyncLoader } from '@sdk/hooks/useAsyncLoader';
import { useOrderWooFulfillment } from '@orderEdit/context/OrderWooFulfillmentContext';

const WooFulfillmentSection: React.FC = () => {
	const { loadFulfillments } = useOrderWooFulfillment();

	// loadFulfillments now handles loading the order internally
	const { loadingStatus, loadingError } = useAsyncLoader(async () => {
		await loadFulfillments();
	}, [loadFulfillments]);

	return (
		<div className="wpo-aom-metabox-section" id="order-fulfillments">
			<Header />
			<WooFulfillmentsList
				loadingStatus={loadingStatus}
				loadingError={loadingError}
			/>
		</div>
	);
};

export default WooFulfillmentSection;
