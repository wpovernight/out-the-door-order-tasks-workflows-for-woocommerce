import path from 'path';
import type { PlaywrightTestConfig } from '@playwright/test';
import { loadE2eEnv } from './env';

/**
 * Where a plugin's logged-in administrator state is saved.
 *
 * @param dir The folder holding the plugin's playwright.config.ts.
 */
export const storageStatePath = (dir: string): string =>
	path.join(dir, '.auth', 'admin.json');

/**
 * Build the Playwright config for a plugin's E2E tests in <dir>.
 *
 * Chromium only, one worker: the tests share one WordPress site and its data.
 * A "setup" project logs in once (auth.setup.ts in <dir>) and saves the
 * session; every test starts from it.
 *
 * @param dir The folder holding the plugin's playwright.config.ts.
 * @return The config.
 */
export function createPlaywrightConfig(dir: string): PlaywrightTestConfig {
	const env = loadE2eEnv(dir);

	return {
		testDir: dir,
		tsconfig: path.join(dir, '..', 'tsconfig.json'),
		outputDir: path.join(dir, 'test-results'),
		fullyParallel: false,
		workers: 1,
		forbidOnly: !!process.env.CI,
		retries: process.env.CI ? 1 : 0,
		reporter: [
			['list'],
			[
				'html',
				{
					open: 'never',
					outputFolder: path.join(dir, 'playwright-report'),
				},
			],
		],
		use: {
			baseURL: env.baseURL,
			// Local sites, DDEV's included, use self-signed certificates.
			ignoreHTTPSErrors: true,
			trace: 'retain-on-failure',
			screenshot: 'only-on-failure',
		},
		projects: [
			{
				name: 'setup',
				testMatch: /auth\.setup\.ts$/,
				use: {
					browserName: 'chromium',
					trace: 'off',
					screenshot: 'off',
				},
			},
			{
				name: 'chromium',
				testMatch: /\.spec\.ts$/,
				dependencies: ['setup'],
				use: {
					browserName: 'chromium',
					viewport: { width: 1440, height: 900 },
					storageState: storageStatePath(dir),
				},
			},
		],
	};
}
