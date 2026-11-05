import type { APIRequestContext, APIResponse } from '@playwright/test';

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface RestOptions {
	/** Query parameters. */
	params?: Record<string, string | number | boolean>;
	/** The JSON body. */
	data?: unknown;
	/** HTTP statuses to accept besides 2xx, e.g. 404 for an idempotent delete. */
	accept?: number[];
}

/**
 * Calls the site's REST API as the logged-in administrator: the session cookie
 * comes from the request context, the nonce WordPress needs alongside it from
 * admin-ajax.php.
 *
 * Callbacks registered with onCleanup() run, newest first, when cleanup() is
 * called; the `rest` fixture calls it after every test, failed or not.
 */
export class RestClient {
	private readonly request: APIRequestContext;
	private nonce?: string;
	private cleanups: Array<() => Promise<unknown>> = [];

	constructor(request: APIRequestContext) {
		this.request = request;
	}

	/**
	 * Call a REST route.
	 *
	 * @param method  The HTTP method.
	 * @param route   The route, e.g. 'wc/v3/wpo/otd/tasks'.
	 * @param options Query, body and extra accepted statuses.
	 * @return The decoded JSON body, or null when there is none.
	 * @throws Error with the status and body when the status is not accepted.
	 */
	async call<T = unknown>(
		method: Method,
		route: string,
		options: RestOptions = {}
	): Promise<T> {
		const response = await this.request.fetch(
			`/wp-json/${route.replace(/^\/+/, '')}`,
			{
				method,
				params: options.params,
				data: options.data,
				headers: {
					'X-WP-Nonce': await this.getNonce(),
					Accept: 'application/json',
				},
			}
		);

		return this.decode<T>(method, route, response, options.accept ?? []);
	}

	get<T = unknown>(route: string, options?: RestOptions): Promise<T> {
		return this.call<T>('GET', route, options);
	}

	post<T = unknown>(route: string, options?: RestOptions): Promise<T> {
		return this.call<T>('POST', route, options);
	}

	put<T = unknown>(route: string, options?: RestOptions): Promise<T> {
		return this.call<T>('PUT', route, options);
	}

	delete<T = unknown>(route: string, options?: RestOptions): Promise<T> {
		return this.call<T>('DELETE', route, options);
	}

	/**
	 * Register a step that undoes something the test created.
	 *
	 * @param callback The step.
	 */
	onCleanup(callback: () => Promise<unknown>): void {
		this.cleanups.push(callback);
	}

	/**
	 * Run every cleanup step, newest first. A failing step does not stop the
	 * others; their errors are thrown together at the end.
	 */
	async cleanup(): Promise<void> {
		const errors: string[] = [];

		while (this.cleanups.length > 0) {
			const callback = this.cleanups.pop()!;
			try {
				await callback();
			} catch (error) {
				errors.push(String(error));
			}
		}

		if (errors.length > 0) {
			throw new Error(`E2E cleanup failed:\n${errors.join('\n')}`);
		}
	}

	private async getNonce(): Promise<string> {
		if (!this.nonce) {
			const response = await this.request.get(
				'/wp-admin/admin-ajax.php',
				{ params: { action: 'rest-nonce' } }
			);
			const text = (await response.text()).trim();

			if (!response.ok() || !/^[0-9a-f]+$/.test(text)) {
				throw new Error(
					`Could not get a REST nonce (HTTP ${response.status()}): is the E2E session logged in?`
				);
			}

			this.nonce = text;
		}

		return this.nonce;
	}

	private async decode<T>(
		method: Method,
		route: string,
		response: APIResponse,
		accept: number[]
	): Promise<T> {
		const text = await response.text();

		if (!response.ok() && !accept.includes(response.status())) {
			throw new Error(
				`${method} ${route} answered HTTP ${response.status()}: ${text}`
			);
		}

		return (text ? JSON.parse(text) : null) as T;
	}
}
