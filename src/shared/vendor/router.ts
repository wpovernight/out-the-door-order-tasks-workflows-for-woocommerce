/**
 * Re-exports react-router-dom as `window.wpo.aom.router`.
 *
 * Add-on views are mounted as route elements inside the core `<HashRouter>`.
 * If an add-on bundled its own react-router-dom it would get a second router
 * context, and the first `useNavigate()`/`<Link>` in an add-on view would throw
 * "useNavigate() may be used only in the context of a <Router> component".
 *
 * Every consumer — including the core's own bundles — resolves react-router-dom
 * to this global. The webpack external deliberately exempts this file, which is
 * what actually pulls the library out of node_modules.
 */
export * from 'react-router-dom';
