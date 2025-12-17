import React from 'react';
import SectionHeader from '@orderEdit/components/common/SectionHeader';
import { useOrderEditData } from '@orderEdit/hooks/useOrderEditData';
import { useOrderFulfillment } from '@orderEdit/context/OrderFulfillmentContext';

const Header: React.FC = () => {
	const { i18n, orderId } = useOrderEditData();
	const { fulfillments } = useOrderFulfillment();

	const fulfillmentCount = fulfillments.length;

	return (
		<SectionHeader
			title="Fulfillments"
			details={`${fulfillmentCount}`}
			actionButtons={[
				<button
					key="add-fulfillment"
					className="wpo-button wpo-button-icon add-button fulfillments-trigger" // This "fulfillments-trigger" class is used to bind the click event
					data-order-id={orderId}
				>
					<span className="screenReader">
						{i18n?.fulfillments?.addFulfillment ||
							'Add Fulfillment'}
					</span>
				</button>,
			]}
		/>
	);
};

export default Header;
