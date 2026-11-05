import type { Page } from '@playwright/test';
import { loadE2eEnv } from './env';
import { storageStatePath } from './config';

/**
 * Log in to wp-admin as the E2E administrator and save the session for the
 * tests to start from.
 *
 * @param page The page to log in with.
 * @param dir  The folder holding the plugin's playwright.config.ts.
 * @throws Error when WordPress refuses the login.
 */
export async function logInAsAdmin(page: Page, dir: string): Promise<void> {
	const { user, password } = loadE2eEnv(dir);

	await page.goto('/wp-login.php');
	await page.locator('#user_login').fill(user);
	await page.locator('#user_pass').fill(password);
	await page.locator('#wp-submit').click();

	const adminBar = page.locator('#wpadminbar');
	const loginError = page.locator('#login_error');
	// WordPress asks administrators now and then to confirm the site email.
	const confirmEmail = page.locator('#correct-admin-email');

	await adminBar.or(loginError).or(confirmEmail).first().waitFor();

	if (await loginError.isVisible()) {
		throw new Error(
			`WordPress refused the login for "${user}": ${(
				await loginError.innerText()
			).trim()}`
		);
	}

	if (await confirmEmail.isVisible()) {
		await confirmEmail.click();
		await adminBar.waitFor();
	}

	await page.context().storageState({ path: storageStatePath(dir) });
}
