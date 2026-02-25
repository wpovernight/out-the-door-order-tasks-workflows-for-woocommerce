export type WooFulfillmentStatus = 'fulfilled' | 'unfulfilled';
export type WooShippingOption = 'tracking-number' | 'manual-entry' | 'no-info';

export type WooFulfillmentMetaItem = {
	id: number;
	key: string;
	value: any;
};

export type WooFulfillmentItem = {
	item_id: number;
	qty: number;
	name?: string;
};

export type WooFulfillment = {
	id: number;
	entity_type: string;
	entity_id: string;
	is_fulfilled: boolean;
	status: WooFulfillmentStatus;
	date_updated: string;
	meta_data: WooFulfillmentMetaItem[];
};

export interface WooFulfillmentMetaData {
	shipping_option?: WooShippingOption;
	tracking_number?: string;
	tracking_url?: string;
	shipment_provider?: string;
	provider_name?: string;
	items?: WooFulfillmentItem[];
	date_fulfilled?: string;
}

export function getWooFulfillmentMeta(
	fulfillment: WooFulfillment
): WooFulfillmentMetaData {
	const meta: WooFulfillmentMetaData = {};

	fulfillment.meta_data.forEach((item) => {
		const key = item.key.replace(/^_/, ''); // Remove leading underscore

		switch (key) {
			case 'shipping_option':
				meta.shipping_option = item.value as WooShippingOption;
				break;
			case 'tracking_number':
				meta.tracking_number = item.value;
				break;
			case 'tracking_url':
				meta.tracking_url = item.value;
				break;
			case 'shipment_provider':
				meta.shipment_provider = item.value;
				break;
			case 'provider_name':
				meta.provider_name = item.value;
				break;
			case 'items':
				meta.items = item.value as WooFulfillmentItem[];
				break;
			case 'date_fulfilled':
				meta.date_fulfilled = item.value;
				break;
		}
	});

	return meta;
}
