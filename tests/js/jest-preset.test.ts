import path from 'path';

const { createJestConfig } = require('./jest-preset');

test('add-on aliases override defaults while keeping shared helpers', () => {
	const rootDir = path.resolve(__dirname, '../..');
	const config = createJestConfig({
		rootDir,
		moduleNameMapper: {
			'^@sdk$': '/addon/sdk.ts',
			'^@addon$': '/addon/index.ts',
		},
	});
	expect(config.moduleNameMapper['^@sdk$']).toBe('/addon/sdk.ts');
	expect(config.moduleNameMapper['^@addon$']).toBe('/addon/index.ts');
	expect(config.moduleNameMapper['^@otd-test-utils$']).toBe(
		path.join(rootDir, 'tests/js/helpers/index.ts')
	);
	expect(config.transform['^.+\\.tsx?$'][1].tsconfig).toBe(
		path.join(rootDir, 'tests/tsconfig.jest.json')
	);
});
