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

	const newRule = page.getByRole('button', { name: 'New rule', exact: true });
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

	const icons = [
		{ selector: '.automations-new-rule-icon', width: 14, height: 14 },
		{ selector: '.automations-chip-arrow', width: 14, height: 14 },
		{ selector: '.automations-pro-card-lock', width: 16, height: 21 },
		{ selector: '.automations-pro-card-link-arrow', width: 12, height: 12 },
	];
	for (const { selector, width, height } of icons) {
		const icon = page.locator(selector).first();
		await expect(icon).toBeVisible();
		expect(await icon.boundingBox()).toMatchObject({ width, height });
		const mask = await icon.evaluate(
			(element) =>
				getComputedStyle(element).maskImage ||
				getComputedStyle(element).webkitMaskImage
		);
		expect(mask).toContain('data:image/svg+xml');
	}

	const radiusOf = (locator: typeof newRule) =>
		locator.evaluate((element) => getComputedStyle(element).borderRadius);
	const searchLook = (locator: typeof newRule) =>
		locator.evaluate((element) => {
			const style = getComputedStyle(element);
			return {
				borderRadius: style.borderRadius,
				border: style.border,
				backgroundColor: style.backgroundColor,
				backgroundImage: style.backgroundImage,
				padding: style.padding,
				fontSize: style.fontSize,
				width: style.width,
			};
		});
	const search = page.getByRole('searchbox', { name: 'Search' });
	const look = await searchLook(search);
	expect(parseFloat(look.borderRadius)).toBeGreaterThan(0);
	expect(await radiusOf(newRule)).toBe(look.borderRadius);
	expect(look.backgroundImage).toContain('data:image/svg+xml');

	await page.mouse.move(0, 0);
	await page.screenshot({
		path: testInfo.outputPath('automations-tab.png'),
		fullPage: true,
	});

	await tabs.getByRole('button', { name: 'Task Manager' }).click();
	expect(
		await searchLook(page.getByRole('searchbox', { name: 'Search' }))
	).toEqual(look);
});
