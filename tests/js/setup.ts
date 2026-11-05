/**
 * Runs before every test file, in core and in add-ons.
 */
import '@testing-library/jest-dom';
import { resetHooks } from './helpers/hooks';
import { clearLocalized } from './helpers/localized';
import { installFetchGuard, takeUnmockedRequests } from './helpers/fetch';

beforeEach(() => {
	installFetchGuard();
});

afterEach(() => {
	resetHooks();
	clearLocalized();

	// Code under test often catches a failed request and only logs it, so a
	// request no test answered would otherwise pass unnoticed.
	const unmocked = takeUnmockedRequests();
	if (unmocked.length > 0) {
		throw new Error(
			`Unmocked fetch requests (answer them with mockFetch()):\n${unmocked.join(
				'\n'
			)}`
		);
	}
});
