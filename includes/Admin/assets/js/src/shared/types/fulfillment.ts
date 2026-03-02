export interface FulfillmentItemAttribute {
    label: string;
    value: string;
}

export interface FulfillmentItem {
    item_id: number;
    name: string;
    image_url: string;
    attributes: FulfillmentItemAttribute[] | string;
    quantity: number;
    fulfilled_quantity: number;
    fulfillment_status: string;
}

export interface FulfillmentOrder {
    order_id: number;
    order_url: string;
    fulfillment_status: string;
    customer_name: string;
    customer_location: {
        city: string;
        state: string;
        country: string;
    };
    items: FulfillmentItem[];
}