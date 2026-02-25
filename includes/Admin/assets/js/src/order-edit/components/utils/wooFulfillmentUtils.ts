import { WooFulfillmentMetaData } from '@shared/types/wooFulfillment';

export const getShippingMethodLabel = (
	fulfillmentMeta: WooFulfillmentMetaData
): string => {
	switch (fulfillmentMeta.shipping_option) {
		case 'tracking-number':
			return (
				fulfillmentMeta.provider_name?.toUpperCase() ||
				fulfillmentMeta.shipment_provider?.toUpperCase() ||
				'Tracking Number' // ToDo: i18n
			);
		case 'manual-entry':
			return 'Manual Entry'; // ToDo: i18n
		case 'no-info':
			return 'No Info'; // ToDo: i18n
		default:
			return 'Unknown'; // ToDo: i18n
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

	try {
		const date = new Date(dateString);
		return date.toLocaleDateString('en-US', {
			month: 'short',
			day: 'numeric',
			year: 'numeric',
		});
	} catch {
		return dateString;
	}
};
