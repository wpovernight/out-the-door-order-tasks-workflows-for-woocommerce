const path = require('path');

module.exports = (env, argv) => {
	const mode = argv.mode || 'development';
	const isProduction = mode === 'production';

	return {
		mode,
		entry: {
			'task-manager': path.resolve(__dirname, 'task-manager/index.tsx'),
			// 'order-edit': path.resolve(__dirname, 'order-edit/index.tsx'),
		},
		output: {
			filename: '[name].js',
			path: path.resolve(__dirname, '..'), // output directly in /js (parent of src)
			clean: false, // don’t delete other files
		},
		resolve: {
			extensions: ['.ts', '.tsx', '.js'],
			alias: {
				'@shared': path.resolve(__dirname, 'shared/'),
				'@taskManager': path.resolve(__dirname, 'task-manager/'),
				'@orderEdit': path.resolve(__dirname, 'order-edit/'),
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
			'react/jsx-runtime': 'React',
			'react/jsx-dev-runtime': 'React',
		},
		devtool: isProduction ? false : 'source-map',
		optimization: {
			minimize: isProduction,
		},
	};
};
