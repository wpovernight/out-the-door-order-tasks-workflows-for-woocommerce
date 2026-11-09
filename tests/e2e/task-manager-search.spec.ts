import { expect, ORDER_MANAGER_URL, test } from './fixtures';

test("the Task Manager's search field keeps core's search look", async ({
	page,
}) => {
	await page.goto(`${ORDER_MANAGER_URL}#/task-manager`);

	const search = page.getByRole('searchbox', { name: 'Search' });
	await expect(search).toBeVisible();
	await expect(search).toHaveClass(/\bwpo-otd-search-input\b/);

	const style = await search.evaluate((element) => {
		const computed = getComputedStyle(element);
		return {
			borderRadius: computed.borderRadius,
			border: `${computed.borderTopWidth} ${computed.borderTopStyle} ${computed.borderTopColor}`,
			padding: computed.padding,
			margin: `${computed.marginTop} ${computed.marginBottom}`,
			fontSize: computed.fontSize,
			width: computed.width,
			color: computed.color,
			backgroundPosition: computed.backgroundPosition,
			backgroundImage: computed.backgroundImage,
		};
	});

	expect(style).toMatchObject({
		borderRadius: '7.02px',
		border: '1px solid rgb(213, 215, 218)',
		padding: '5.1948px 14.04px 5.1948px 42.12px',
		margin: '7.02px 7.02px',
		fontSize: '14.04px',
		color: 'rgb(51, 51, 51)',
		backgroundPosition: '11.232px 50%',
	});
	expect(parseFloat(style.width)).toBeCloseTo(280.8, 0);
	expect(style.backgroundImage).toContain('data:image/svg+xml');

	await search.focus();
	await expect(search).toHaveCSS('border-top-color', 'rgb(110, 30, 220)');
});
