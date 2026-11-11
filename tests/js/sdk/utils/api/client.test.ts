import { ApiError, handleEnvelope } from '@sdk';

/**
 * A minimal failed fetch Response with this status and body.
 *
 * @param status The HTTP status.
 * @param body   The body; anything but a string is sent as JSON.
 */
const failed = (status: number, body: unknown): Response => {
	const text = 'string' === typeof body ? body : JSON.stringify(body);

	return {
		ok: false,
		status,
		text: async () => text,
		json: async () => JSON.parse(text),
	} as unknown as Response;
};

/**
 * The error handleEnvelope throws for this response.
 *
 * @param response The response.
 */
const errorOf = async (response: Response): Promise<ApiError> => {
	try {
		await handleEnvelope(response);
	} catch (error) {
		return error as ApiError;
	}

	throw new Error('handleEnvelope did not throw');
};

describe('ApiError', () => {
	it('keeps every message in data.errors, the first as its message', async () => {
		const error = await errorOf(
			failed(409, {
				code: 'rule_not_activatable',
				message: 'The rule cannot be switched on as it is saved.',
				data: {
					status: 409,
					errors: {
						'definition.triggers[0]': ['Not registered.'],
						'definition.actions[1].config.url': [
							'Must be a URL.',
							'Must use https.',
						],
					},
				},
			})
		);

		expect(error).toBeInstanceOf(ApiError);
		expect(error.status).toBe(409);
		expect(error.code).toBe('rule_not_activatable');
		expect(error.message).toBe('Not registered.');
		expect(error.data).toEqual({
			status: 409,
			errors: {
				'definition.triggers[0]': ['Not registered.'],
				'definition.actions[1].config.url': [
					'Must be a URL.',
					'Must use https.',
				],
			},
		});
	});

	it('keeps data beside a plain message', async () => {
		const error = await errorOf(
			failed(409, {
				code: 'order_stale',
				message: 'Reload it and try again.',
				data: { status: 409, ids: [3, 1] },
			})
		);

		expect(error.message).toBe('Reload it and try again.');
		expect(error.data).toEqual({ status: 409, ids: [3, 1] });
	});

	it.each([
		['no data', { code: 'x', message: 'Nope.' }],
		['a list as data', { code: 'x', message: 'Nope.', data: [1] }],
		['a string as data', { code: 'x', message: 'Nope.', data: 'no' }],
	])('has no data for %s', async (_label, body) => {
		const error = await errorOf(failed(400, body));

		expect(error.message).toBe('Nope.');
		expect(error.data).toBeUndefined();
	});

	it('has no data for a body that is not JSON', async () => {
		const error = await errorOf(failed(500, 'Fatal error'));

		expect(error.message).toBe('Fatal error');
		expect(error.code).toBeUndefined();
		expect(error.data).toBeUndefined();
	});
});
