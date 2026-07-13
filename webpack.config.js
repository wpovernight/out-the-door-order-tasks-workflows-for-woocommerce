const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = (env, argv) => {
	const mode = argv.mode || 'development';
	const isProduction = mode === 'production';

	return {
		mode,
		entry: {
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
				'@shared': path.resolve(__dirname, 'src/shared/'),
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
		// Prevent bundling dependencies that are provided by WordPress.
		externals: {
			react: 'React',
			'react-dom': 'ReactDOM',
			'@wordpress/i18n': ['wp', 'i18n'],
			'@wordpress/hooks': ['wp', 'hooks'],
			'@wordpress/element': ['wp', 'element'],
		},
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
