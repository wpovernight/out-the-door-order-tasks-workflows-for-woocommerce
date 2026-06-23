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
        const errorText = await response.text();
        throw new Error(`API request failed: ${response.status}: ${errorText}`);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return response.json() as Promise<T>;
}