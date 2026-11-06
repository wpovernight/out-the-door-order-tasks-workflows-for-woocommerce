import { expect, ORDER_MANAGER_URL, test } from './fixtures';

const EXAMPLES = [
	'Flag orders from one client',
	'Route embroidery to the right team',
	'Keep order status in step with your board',
];

test('the Automations tab previews example rules and Pro', async ({
	page,
}, testInfo) => {
	await page.goto(ORDER_MANAGER_URL);

	const tabs = page.locator('#main-tabs');
	await tabs.getByRole('button', { name: 'Automations' }).click();

	await expect(page).toHaveURL(/#\/automations$/);
	await expect(tabs.locator('li.active')).toHaveText('Automations');
	await expect(
		page.getByRole('heading', { name: 'Active Rules' })
	).toBeVisible();

	for (const title of EXAMPLES) {
		await expect(
			page.getByRole('heading', { name: new RegExp(`^${title}`) })
		).toBeVisible();
	}
	await expect(page.getByRole('switch')).toHaveCount(3);
	await expect(page.getByText('+ 1 action')).toBeVisible();

	const newRule = page.getByRole('button', { name: /New rule/ });
	await expect(newRule).toHaveAttribute('aria-disabled', 'true');
	await newRule.hover();
	await expect(
		page.getByRole('tooltip', { name: 'Available in Pro' })
	).toBeVisible();
	const header = await page.locator('.automations-header').boundingBox();
	expect(header).not.toBeNull();
	await page.screenshot({
		path: testInfo.outputPath('automations-hint.png'),
		clip: {
			...(header as NonNullable<typeof header>),
			height: (header?.height ?? 0) + 48,
		},
		animations: 'disabled',
	});

	await expect(
		page.getByRole('heading', { name: /^Rules\s*Pro$/ })
	).toBeVisible();
	await expect(
		page.getByRole('link', { name: /See what's in Pro - \$99\/year/ })
	).toBeVisible();

	await page.mouse.move(0, 0);
	await page.screenshot({
		path: testInfo.outputPath('automations-tab.png'),
		fullPage: true,
	});
});
