import type {
	PlaywrightTestArgs,
	PlaywrightTestOptions,
	PlaywrightWorkerArgs,
	PlaywrightWorkerOptions,
	TestType,
} from '@playwright/test';
import { RestClient } from './rest';

export interface OtdFixtures {
	/** The REST API as the logged-in administrator; cleans up after the test. */
	rest: RestClient;
}

type BaseTest = TestType<
	PlaywrightTestArgs & PlaywrightTestOptions,
	PlaywrightWorkerArgs & PlaywrightWorkerOptions
>;

/**
 * Add the Out the Door fixtures to a plugin's own `test`.
 *
 * The plugin passes its own `test` from '@playwright/test' rather than this
 * file importing one, so a run never loads two copies of Playwright.
 *
 * @param base The plugin's `test` from '@playwright/test'.
 * @return The extended `test`.
 */
export const withOtdFixtures = (base: BaseTest) =>
	base.extend<OtdFixtures>({
		rest: async ({ page }, use) => {
			// The page's request context shares the browser's session cookies.
			const rest = new RestClient(page.request);
			try {
				await use(rest);
			} finally {
				await rest.cleanup();
			}
		},
	});

/**
 * A unique name for data a test creates, with the "E2E" prefix that marks
 * test data on a shared site.
 *
 * @param label What the data is, e.g. 'Tabs status'.
 */
export const e2eName = (label: string): string =>
	`E2E ${label} ${Date.now().toString(36)}${Math.random()
		.toString(36)
		.slice(2, 6)}`;
