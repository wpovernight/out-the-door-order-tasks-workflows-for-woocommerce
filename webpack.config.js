const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');
const SDK_DIR = path.resolve(__dirname, 'src/sdk');
const ROUTER_SHIM = path.resolve(__dirname, 'src/sdk/vendor/router.ts');

module.exports = (env, argv) => {
	const mode = argv.mode || 'development';
	const isProduction = mode === 'production';

	return {
		mode,
		entry: {
			sdk: {
				import: path.resolve(__dirname, 'src/sdk/index.ts'),
				library: { name: ['wpo', 'otd', 'sdk'], type: 'window' },
			},
			router: {
				import: ROUTER_SHIM,
				library: { name: ['wpo', 'otd', 'router'], type: 'window' },
			},
			'order-manager': path.resolve(__dirname, 'src/order-manager/index.tsx'),
			'order-edit-metabox': path.resolve(__dirname, 'src/order-edit/index.tsx'),
			// Non-React admin script
			'order-edit': path.resolve(__dirname, 'src/order-edit/fulfillment.js'),
		},
		output: {
			filename: '[name].js',
			path: path.resolve(__dirname, 'assets/js'),
			clean: false, // don’t delete other files
		},
		resolve: {
			extensions: ['.ts', '.tsx', '.js'],
			alias: {
				'@sdk': path.resolve(__dirname, 'src/sdk/'),
				'@orderManager': path.resolve(__dirname, 'src/order-manager/'),
				'@taskManager': path.resolve(__dirname, 'src/task-manager/'),
				'@orderEdit': path.resolve(__dirname, 'src/order-edit/'),
			},
		},
		module: {
			rules: [
				{
					test: /\.tsx?$/,
					use: 'ts-loader',
					exclude: /node_modules/,
				},
			],
		},
		// Prevent bundling dependencies that are provided by WordPress, plus our own
		// SDK runtime (see the entries above).
		externals: [
			{
				react: 'React',
				'react-dom': 'ReactDOM',
				'@wordpress/i18n': ['wp', 'i18n'],
				'@wordpress/hooks': ['wp', 'hooks'],
				'@wordpress/element': ['wp', 'element'],
			},
			({ context, request, contextInfo }, callback) => {
				const issuer = contextInfo && contextInfo.issuer;

				if (request === 'react-router-dom' && issuer !== ROUTER_SHIM) {
					return callback(null, ['wpo', 'otd', 'router'], 'window');
				}

				if (
					request &&
					request.startsWith('@sdk') &&
					!(context || '').startsWith(SDK_DIR)
				) {
					return callback(null, ['wpo', 'otd', 'sdk'], 'window');
				}

				return callback();
			},
		],
		devtool: isProduction ? false : 'source-map',
		optimization: {
			minimize: isProduction,
			minimizer: [
				new TerserPlugin({
					extractComments: false,
				}),
			],
		},
	};
};