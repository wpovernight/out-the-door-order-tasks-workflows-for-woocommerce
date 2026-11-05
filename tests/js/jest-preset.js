/**
 * Shared Jest configuration for Out the Door and its add-ons.
 *
 * On a WordPress page React, the wp.* packages and react-router-dom exist once,
 * and the core SDK is the only copy of '@sdk'. An add-on's tests import the
 * core SDK source, which would resolve its packages from this plugin's
 * node_modules while the add-on's own files resolve them from the add-on's:
 * two Reacts, and every hook fails. So the resolver (resolver.js) takes every
 * shared runtime package from this plugin's copy, whichever repository the
 * tests run from.
 *
 * Usage, from a jest.config.js:
 *
 *     module.exports = require('<core>/tests/js/jest-preset').createJestConfig({
 *         rootDir: path.resolve(__dirname, '../..'),
 *     });
 */
const fs = require('fs');
const path = require('path');
const { SHARED_PACKAGES } = require('./resolver');

const CORE_DIR = path.resolve(__dirname, '../..');

/**
 * Fail early, with the fix, when core's packages are not installed.
 */
const assertCoreInstalled = () => {
	const missing = ['ts-jest', ...SHARED_PACKAGES].filter(
		(name) =>
			!fs.existsSync(
				path.join(CORE_DIR, 'node_modules', name, 'package.json')
			)
	);

	if (missing.length > 0) {
		throw new Error(
			`Out the Door's JS tests need ${missing.join(
				', '
			)} in ${CORE_DIR}/node_modules: run "npm ci" there.`
		);
	}
};

/**
 * Module aliases: the ones core's tsconfig.json and webpack config define,
 * plus the shared test helpers.
 */
const ALIASES = {
	'^@sdk$': path.join(CORE_DIR, 'src/sdk/index.ts'),
	'^@sdk/(.*)$': path.join(CORE_DIR, 'src/sdk/$1'),
	'^@orderManager/(.*)$': path.join(CORE_DIR, 'src/order-manager/$1'),
	'^@taskManager/(.*)$': path.join(CORE_DIR, 'src/task-manager/$1'),
	'^@orderEdit/(.*)$': path.join(CORE_DIR, 'src/order-edit/$1'),
	'^@otd-test-utils$': path.join(CORE_DIR, 'tests/js/helpers/index.ts'),
};

/**
 * Build a Jest config for a plugin whose tests live in <rootDir>/tests/js and
 * are type-checked by <rootDir>/tests/tsconfig.json. The add-on must also
 * supply tests/tsconfig.jest.json with isolatedModules enabled.
 *
 * @param {Object} options
 * @param {string} options.rootDir            The plugin's root folder.
 * @param {Object} [options.moduleNameMapper] Aliases overriding the core defaults.
 * @return {import('jest').Config} The Jest config.
 */
const createJestConfig = ({ rootDir, moduleNameMapper = {} }) => {
	assertCoreInstalled();

	return {
		rootDir,
		roots: ['<rootDir>/tests/js'],
		testMatch: ['**/*.test.ts', '**/*.test.tsx'],
		testEnvironment: require.resolve('jest-environment-jsdom', {
			paths: [CORE_DIR],
		}),
		setupFiles: [path.join(CORE_DIR, 'tests/js/environment.ts')],
		setupFilesAfterEnv: [path.join(CORE_DIR, 'tests/js/setup.ts')],
		resolver: path.join(CORE_DIR, 'tests/js/resolver.js'),
		moduleFileExtensions: ['ts', 'tsx', 'js', 'jsx', 'json'],
		moduleNameMapper: { ...ALIASES, ...moduleNameMapper },
		transform: {
			'^.+\\.tsx?$': [
				require.resolve('ts-jest', { paths: [CORE_DIR] }),
				{ tsconfig: path.join(rootDir, 'tests/tsconfig.jest.json') },
			],
		},
		collectCoverageFrom: ['<rootDir>/src/**/*.{ts,tsx}', '!**/*.d.ts'],
		coverageDirectory: '<rootDir>/coverage',
		clearMocks: true,
		restoreMocks: true,
	};
};

module.exports = { CORE_DIR, createJestConfig };
