import { test as setup } from '@playwright/test';
import { logInAsAdmin } from '@otd-e2e';

setup('log in as the E2E administrator', async ({ page }) => {
	await logInAsAdmin(page, __dirname);
});
