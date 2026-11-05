import { test as base } from '@playwright/test';
import { withOtdFixtures } from '@otd-e2e';

export { expect } from '@playwright/test';
export { e2eName } from '@otd-e2e';

export const test = withOtdFixtures(base);

export const ORDER_MANAGER_URL =
	'/wp-admin/admin.php?page=wpo_otd_order_manager';
