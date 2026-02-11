export type FulfillmentStatus = 'fulfilled' | 'unfulfilled';
export type ShippingOption = 'tracking-number' | 'manual-entry' | 'no-info';

export type FulfillmentMetaItem = {
	id: number;
	key: string;
	value: any;
};

export type FulfillmentItem = {
	item_id: number;
	qty: number;
	name?: string;
};

export type Fulfillment = {
	id: number;
	entity_type: string;
	entity_id: string;
	is_fulfilled: boolean;
	status: FulfillmentStatus;
	date_updated: string;
	meta_data: FulfillmentMetaItem[];
};

export interface FulfillmentMetaData {
	shipping_option?: ShippingOption;
	tracking_number?: string;
	tracking_url?: string;
	shipment_provider?: string;
	provider_name?: string;
	items?: FulfillmentItem[];
	date_fulfilled?: string;
}

export function getFulfillmentMeta(
	fulfillment: Fulfillment
): FulfillmentMetaData {
	const meta: FulfillmentMetaData = {};

	fulfillment.meta_data.forEach((item) => {
		const key = item.key.replace(/^_/, ''); // Remove leading underscore

		switch (key) {
			case 'shipping_option':
				meta.shipping_option = item.value as ShippingOption;
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
				meta.items = item.value as FulfillmentItem[];
				break;
			case 'date_fulfilled':
				meta.date_fulfilled = item.value;
				break;
		}
	});

	return meta;
}
