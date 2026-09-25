function getApiConfig() {
	const orderManagerData = (window as any).WPO_AOM_OrderManager;
	const orderEditMetaBoxData = (window as any).WPO_AOM_OrderEdit_MetaBox;

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

async function extractErrorMessage(response: Response): Promise<string> {
	const fallback = `Request failed (${response.status}).`;

	const raw = await response.text();
	if (!raw) {
		return fallback;
	}

	try {
		const body = JSON.parse(raw);

		const fieldErrors = body?.data?.errors;
		if (fieldErrors && typeof fieldErrors === 'object') {
			const first = Object.values(fieldErrors)[0];
			if (Array.isArray(first) && first.length > 0) {
				return String(first[0]);
			}
			if (typeof first === 'string') {
				return first;
			}
		}

		if (typeof body?.message === 'string' && body.message) {
			return body.message;
		}
	} catch {
		// Non-JSON body — fall through to the raw text.
		return raw;
	}

	return fallback;
}

/**
 * Handles the API response, checking for errors and parsing JSON.
 *
 * @template T - The expected type of the response data.
 * @param  response
 *
 * @return {Promise<T>} The parsed JSON data.
 * @throws Will throw an error if the response is not ok.
 */
export async function handleResponse<T>(response: Response): Promise<T> {
	if (!response.ok) {
		throw new Error(await extractErrorMessage(response));
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
