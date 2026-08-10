export interface CustomOrderStatus {
	id: number;
	status_key: string;
	label: string;
	background: `#${string}` | `rgb(${number},${number},${number})` | string;
	foreground: `#${string}` | `rgb(${number},${number},${number})` | string;
	is_deleting: boolean;
}
