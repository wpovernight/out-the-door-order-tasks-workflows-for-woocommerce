import { e2eName, expect, ORDER_MANAGER_URL, test } from './fixtures';

const STATUSES_ROUTE = 'wc/v3/wpo/otd/custom-order-statuses';

test('the Out the Door page switches between its tabs', async ({
	page,
	rest,
}) => {
	const label = e2eName('Tabs status');
	const { data: status } = await rest.post<{ data: { id: number } }>(
		STATUSES_ROUTE,
		{ data: { label, background: '#2271b1' } }
	);
	rest.onCleanup(() =>
		rest.delete(`${STATUSES_ROUTE}/${status.id}`, { accept: [404] })
	);

	await page.goto(ORDER_MANAGER_URL);

	const tabs = page.locator('#main-tabs');
	await expect(page).toHaveURL(/#\/dashboard$/);
	await expect(tabs.locator('li.active')).toHaveText('Dashboard');

	await tabs.getByRole('button', { name: 'Custom Order Status' }).click();

	await expect(page).toHaveURL(/#\/custom-order-status$/);
	await expect(tabs.locator('li.active')).toHaveText('Custom Order Status');
	await expect(page.getByRole('cell', { name: label })).toBeVisible();

	await tabs.getByRole('button', { name: 'Task Manager' }).click();

	await expect(page).toHaveURL(/#\/task-manager/);
	await expect(tabs.locator('li.active')).toHaveText('Task Manager');
	await expect(page.getByRole('cell', { name: label })).toBeHidden();
});
