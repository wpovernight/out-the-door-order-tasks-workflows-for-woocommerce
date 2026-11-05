import { TEST_API_NAMESPACE, TEST_API_ROOT } from './localized';

/**
 * A canned answer: a body sent with status 200, or an explicit status and body.
 */
export type MockAnswer =
	| { status: number; body?: unknown }
	| ((request: RecordedRequest) => { status: number; body?: unknown });

export interface RecordedRequest {
	method: string;
	/** The path after the API namespace, without the query, e.g. '/tasks/7'. */
	path: string;
	url: string;
	query: URLSearchParams;
	body: unknown;
	headers: Record<string, string>;
}

export interface FetchMock {
	/** Every request answered so far, oldest first. */
	calls: RecordedRequest[];
}

let unmocked: string[] = [];

const API_PREFIX = new URL(`${TEST_API_ROOT}/${TEST_API_NAMESPACE}`).pathname;

/**
 * A minimal stand-in for the fetch Response; jsdom has none.
 *
 * @param status The HTTP status.
 * @param body   The body; anything but a string is sent as JSON.
 */
function makeResponse(status: number, body: unknown): Response {
	let text = '';
	if (undefined !== body && 204 !== status) {
		text = 'string' === typeof body ? body : JSON.stringify(body);
	}

	return {
		ok: status >= 200 && status < 300,
		status,
		text: async () => text,
		json: async () => JSON.parse(text),
	} as unknown as Response;
}

function describeRequest(input: RequestInfo | URL, init?: RequestInit) {
	const method = (init?.method ?? 'GET').toUpperCase();
	const url = new URL(String(input), TEST_API_ROOT);
	const path = url.pathname.startsWith(API_PREFIX)
		? url.pathname.slice(API_PREFIX.length)
		: url.pathname;

	let body: unknown = init?.body;
	if ('string' === typeof body) {
		try {
			body = JSON.parse(body);
		} catch {
			// Not JSON: keep the text.
		}
	}

	return {
		method,
		path,
		url: url.toString(),
		query: url.searchParams,
		body,
		headers: { ...(init?.headers as Record<string, string>) },
	};
}

/**
 * Make every unanswered fetch fail the test: it is rejected and recorded, and
 * the setup file throws after the test if any were recorded.
 */
export function installFetchGuard(): void {
	unmocked = [];
	global.fetch = jest.fn(
		async (input: RequestInfo | URL, init?: RequestInit) => {
			const request = describeRequest(input, init);
			unmocked.push(`${request.method} ${request.url}`);
			throw new Error(`Unmocked fetch: ${request.method} ${request.url}`);
		}
	) as typeof fetch;
}

/**
 * The unmocked requests recorded since the last call, which clears them.
 */
export function takeUnmockedRequests(): string[] {
	const taken = unmocked;
	unmocked = [];
	return taken;
}

/**
 * Answer the core REST API's requests from a table of routes.
 *
 * Keys are "METHOD /path", the path after the API namespace, without the query:
 *
 *     mockFetch( {
 *         'GET /tasks/fields/priority/options': ok( envelope( options ) ),
 *         'DELETE /tasks/7': { status: 204 },
 *     } );
 *
 * A request no key matches fails the test, as with no mock at all.
 *
 * @param routes The answers, by "METHOD /path".
 * @return The recorded calls.
 */
export function mockFetch(routes: Record<string, MockAnswer>): FetchMock {
	const mock: FetchMock = { calls: [] };

	global.fetch = jest.fn(
		async (input: RequestInfo | URL, init?: RequestInit) => {
			const request = describeRequest(input, init);
			const answer = routes[`${request.method} ${request.path}`];

			if (!answer) {
				unmocked.push(`${request.method} ${request.url}`);
				throw new Error(
					`Unmocked fetch: ${request.method} ${request.url}`
				);
			}

			mock.calls.push(request);
			const { status, body } =
				'function' === typeof answer ? answer(request) : answer;

			return makeResponse(status, body);
		}
	) as typeof fetch;

	return mock;
}

/**
 * A 200 answer.
 *
 * @param body The body.
 */
export const ok = (body: unknown): MockAnswer => ({ status: 200, body });

/**
 * Wrap data in the { data, meta } envelope the core endpoints answer with.
 *
 * @param data The data.
 * @param meta The meta, if any.
 */
export const envelope = (data: unknown, meta?: unknown) =>
	undefined === meta ? { data } : { data, meta };
