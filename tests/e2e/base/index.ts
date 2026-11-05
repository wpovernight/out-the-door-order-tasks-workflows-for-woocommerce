/**
 * Playwright base shared by core and add-ons, imported as '@otd-e2e'.
 *
 * Only type imports reach '@playwright/test' here: a plugin passes in its own
 * `test`, so a run never loads core's copy of Playwright beside its own.
 */
export * from './auth';
export * from './config';
export * from './env';
export * from './fixtures';
export * from './rest';
