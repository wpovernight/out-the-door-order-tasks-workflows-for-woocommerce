import fs from 'fs';
import path from 'path';

export interface E2eEnv {
	baseURL: string;
	user: string;
	password: string;
}

const REQUIRED = {
	baseURL: 'OTD_E2E_BASE_URL',
	user: 'OTD_E2E_USER',
	password: 'OTD_E2E_PASSWORD',
} as const;

/**
 * Read the E2E settings from the environment, after loading <dir>/.env if it
 * exists. Variables already set in the environment win over the file.
 *
 * @param dir The folder holding the plugin's playwright.config.ts.
 * @return The settings.
 * @throws Error naming every missing variable.
 */
export function loadE2eEnv(dir: string): E2eEnv {
	const file = path.join(dir, '.env');
	if (fs.existsSync(file)) {
		process.loadEnvFile(file);
	}

	const missing = Object.values(REQUIRED).filter(
		(name) => !process.env[name]
	);
	if (missing.length > 0) {
		throw new Error(
			`Set ${missing.join(', ')} in the environment or in ${file} ` +
				'(base URL of the WordPress site, and an administrator login).'
		);
	}

	return {
		baseURL: process.env[REQUIRED.baseURL]!.replace(/\/+$/, ''),
		user: process.env[REQUIRED.user]!,
		password: process.env[REQUIRED.password]!,
	};
}
