import { __ } from '@wordpress/i18n';
import { WooFulfillmentMetaData } from '@sdk';

export const getShippingMethodLabel = (
	fulfillmentMeta: WooFulfillmentMetaData
): string => {
	switch (fulfillmentMeta.shipping_option) {
		case 'tracking-number':
			return (
				fulfillmentMeta.provider_name?.toUpperCase() ||
				fulfillmentMeta.shipment_provider?.toUpperCase() ||
				__('Tracking Number', 'advanced-order-manager')
			);
		case 'manual-entry':
			return __('Manual Entry', 'advanced-order-manager');
		case 'no-info':
			return __('No Info', 'advanced-order-manager');
		default:
			return __('Unknown', 'advanced-order-manager');
	}
};

export const getTrackingInfo = (
	fulfillmentMeta: WooFulfillmentMetaData
): string => {
	if (fulfillmentMeta.tracking_number) {
		return fulfillmentMeta.tracking_number;
	}
	if (fulfillmentMeta.tracking_url) {
		return fulfillmentMeta.tracking_url;
	}
	return '';
};

export const formatDate = (dateString?: string) => {
	if (!dateString) {
		return '';
	}

	const date = new Date(dateString);
	if (isNaN(date.getTime())) {
		return dateString;
	}

	return date.toLocaleDateString(undefined, {
		month: 'short',
		day: 'numeric',
		year: 'numeric',
	});
};
