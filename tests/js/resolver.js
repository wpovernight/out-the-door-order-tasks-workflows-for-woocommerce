/**
 * Jest resolver: resolves the shared runtime packages from core's
 * node_modules, whichever file imports them, so a test run has one copy of
 * each, as a WordPress page does. Everything else resolves as usual.
 */
const path = require('path');

const CORE_DIR = path.resolve(__dirname, '../..');

const SHARED_PACKAGES = [
	'react',
	'react-dom',
	'react-router-dom',
	'@wordpress/element',
	'@wordpress/hooks',
	'@wordpress/i18n',
	'@testing-library/dom',
	'@testing-library/jest-dom',
	'@testing-library/react',
	'@testing-library/user-event',
];

/**
 * Whether a request is one of the shared packages or a subpath of one.
 *
 * @param {string} request The module request.
 * @return {boolean} Whether it is shared.
 */
const isShared = (request) =>
	SHARED_PACKAGES.some(
		(name) => request === name || request.startsWith(`${name}/`)
	);

/**
 * @param {string} request The module request.
 * @param {Object} options Jest's resolver options.
 * @return {string} The resolved file.
 */
module.exports = (request, options) =>
	options.defaultResolver(
		request,
		isShared(request) ? { ...options, basedir: CORE_DIR } : options
	);

module.exports.SHARED_PACKAGES = SHARED_PACKAGES;
