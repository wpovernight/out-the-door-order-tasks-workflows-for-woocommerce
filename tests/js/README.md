# Shared JavaScript tests

Run `npm ci` in core before running an add-on's tests. The shared Jest factory
uses ts-jest with isolated modules; `npm run typecheck` checks types separately.
This reuses the TypeScript compiler configuration without adding Babel.
Add-ons supply `tests/tsconfig.jest.json` extending their test tsconfig with
`compilerOptions.isolatedModules: true`. `moduleNameMapper` overrides are merged
over core's defaults. Shared React, WordPress and testing libraries resolve from
core so providers and consumers use the same runtime instances.

Hooks are reset after every test. To test a module that registers hooks on import,
load both the module and WordPress hooks inside `jest.isolateModules` in each test:

```ts
jest.isolateModules(() => {
    const { applyFilters } = require('@wordpress/hooks');
    require('../../src/order-manager');
    expect(applyFilters('my.filter', input)).toEqual(expected);
});
```

Keep all imports and assertions involving that registry inside the callback.
Do not import the registration module at file scope: Jest caches it after its
first import, while test teardown removes its filters. For component tests use
`renderWithProviders`, which wraps the real SDK providers, and `mockFetch` for
requests. The setup unmounts components before checking unexpected requests.

# Browser tests

Set `OTD_E2E_BASE_URL`, `OTD_E2E_USER`, `OTD_E2E_PASSWORD` in the shell or
`tests/e2e/.env` (gitignored). A dedicated administrator account for the tests
(for example `e2e-admin`) is recommended over a personal one. Add-ons
reuse `tests/e2e/base` through a test-only sibling path. Run
`npx playwright install chromium`, then `npm run test:e2e` against the built site.
No system packages are installed by these scripts. Authentication artifacts and
reports are ignored; authentication traces and screenshots are disabled.
The REST fixture shares the browser cookies, obtains a WordPress nonce, and runs
registered cleanup callbacks even after failures. Prefix created data with E2E.

CI would need Node 22, a sibling core checkout with `npm ci` in both plugins,
typechecking, Jest, and a WordPress/WooCommerce site with both plugins active and
built for Playwright. Install Chromium and provide credentials through CI secrets.
