import React from 'react';
import { __ } from '@wordpress/i18n';
import SectionHeader from '@orderEdit/components/common/SectionHeader';
import { useOrderEditData } from '@orderEdit/hooks/useOrderEditData';
import { useOrderWooFulfillment } from '@orderEdit/context/OrderWooFulfillmentContext';

const Header: React.FC = () => {
	const { orderId } = useOrderEditData();
	const { fulfillments } = useOrderWooFulfillment();

	const fulfillmentCount = fulfillments.length;

	return (
		<SectionHeader
			title={__('Fulfillments', 'advanced-order-manager-for-woocommerce')}
			details={`${fulfillmentCount}`}
			actionButtons={[
				<button
					key="add-fulfillment"
					className="wpo-button wpo-button-icon add-button fulfillments-trigger" // This "fulfillments-trigger" class is used to bind the click event
					data-order-id={orderId}
				>
					<span className="screen-reader-text">
						{__('Add Fulfillment', 'advanced-order-manager-for-woocommerce')}
					</span>
				</button>,
			]}
		/>
	);
};

export default Header;
