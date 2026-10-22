function getApiConfig() {
	const orderManagerData = (window as any).WPO_OTD_OrderManager;
	const orderEditMetaBoxData = (window as any).WPO_OTD_OrderEdit_MetaBox;

	const config = orderManagerData || orderEditMetaBoxData;

	if (!config) {
		console.warn('⚠️ API configuration not found. API calls will fail.');
		return { apiRoot: '', apiNamespace: '', nonce: '' };
	}

	return {
		apiRoot: config.apiRoot,
		apiNamespace: config.apiNamespace,
		nonce: config.nonce,
	};
}

export function getApiRoot(): string {
	return getApiConfig().apiRoot;
}

export function getApiNamespace(): string {
	return getApiConfig().apiNamespace;
}

export function getHeaders(): Record<string, string> {
	return {
		'Content-Type': 'application/json',
		'X-WP-Nonce': getApiConfig().nonce,
	};
}

/**
 * A failed API request. The code is the error code the endpoint answered with, when it sent one.
 */
export class ApiError extends Error {
	readonly code?: string;
	readonly status: number;

	constructor(message: string, status: number, code?: string) {
		super(message);
		this.name = 'ApiError';
		this.status = status;
		this.code = code;
	}
}

async function extractError(response: Response): Promise<ApiError> {
	const fallback = `Request failed (${response.status}).`;

	const raw = await response.text();
	if (!raw) {
		return new ApiError(fallback, response.status);
	}

	let body: any;
	try {
		body = JSON.parse(raw);
	} catch {
		// Non-JSON body — fall through to the raw text.
		return new ApiError(raw, response.status);
	}

	const code = typeof body?.code === 'string' ? body.code : undefined;

	const fieldErrors = body?.data?.errors;
	if (fieldErrors && typeof fieldErrors === 'object') {
		const first = Object.values(fieldErrors)[0];
		if (Array.isArray(first) && first.length > 0) {
			return new ApiError(String(first[0]), response.status, code);
		}
		if (typeof first === 'string') {
			return new ApiError(first, response.status, code);
		}
	}

	if (typeof body?.message === 'string' && body.message) {
		return new ApiError(body.message, response.status, code);
	}

	return new ApiError(fallback, response.status, code);
}

/**
 * Handles the API response, checking for errors and parsing JSON.
 *
 * @template T - The expected type of the response data.
 * @param  response
 *
 * @return {Promise<T>} The parsed JSON data.
 * @throws Will throw an ApiError if the response is not ok.
 */
export async function handleResponse<T>(response: Response): Promise<T> {
	if (!response.ok) {
		throw await extractError(response);
	}

	if (response.status === 204) {
		return undefined as T;
	}

	return response.json() as Promise<T>;
}

/**
 * The uniform response wrapper for our wc/v3/wpo/otd/* endpoints.
 */
export interface Envelope<TData, TMeta = undefined> {
	data: TData;
	meta?: TMeta;
}

export interface PaginationMeta {
	current_page: number;
	per_page: number;
	last_page: number;
	total: number;
	from: number | null;
	to: number | null;
}

/** A paginated collection: an envelope whose meta is always present. */
export interface Paginated<T> {
	data: T[];
	meta: PaginationMeta;
}

/**
 * Unwraps the { data } envelope used by our endpoints. WC core
 * endpoints are not enveloped, keep using handleResponse for those.
 * @param response
 */
export async function handleEnvelope<T>(response: Response): Promise<T> {
	const body = await handleResponse<Envelope<T> | undefined>(response);
	return body?.data as T;
}

export async function handlePaginated<T>(response: Response): Promise<Paginated<T>> {
	return handleResponse<Paginated<T>>(response);
}
