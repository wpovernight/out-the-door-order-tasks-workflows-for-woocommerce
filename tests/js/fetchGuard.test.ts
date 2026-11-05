import { spawnSync } from 'child_process';
import path from 'path';

const root = path.resolve(__dirname, '../..');
const { createJestConfig } = require('./jest-preset');

test('Jest fails caught unmocked requests, including component unmounts', () => {
	const config = {
		...createJestConfig({ rootDir: root }),
		testMatch: ['**/unmocked.fixture.tsx'],
	};
	const result = spawnSync(
		process.execPath,
		[
			require.resolve('jest/bin/jest'),
			'--config',
			JSON.stringify(config),
			'--runInBand',
			'--json',
			'--no-cache',
		],
		{ cwd: root, encoding: 'utf8', timeout: 20000 }
	);

	expect(result.error).toBeUndefined();
	expect(result.status).toBe(1);
	const report = JSON.parse(result.stdout);
	expect(report.numTotalTests).toBe(2);
	expect(report.numFailedTests).toBe(2);
	for (const assertion of report.testResults[0].assertionResults) {
		expect(assertion.failureMessages.join('\n')).toContain(
			'Unmocked fetch requests'
		);
	}
}, 25000);
