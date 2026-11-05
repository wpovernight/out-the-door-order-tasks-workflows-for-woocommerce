const path = require('path');
const { createJestConfig } = require('./jest-preset');

module.exports = createJestConfig({
	rootDir: path.resolve(__dirname, '../..'),
});
