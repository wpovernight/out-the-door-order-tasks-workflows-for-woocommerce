const path = require('path');

module.exports = (env, argv) => {
    const mode = argv.mode || 'development';
    const isProduction = mode === 'production';

    return {
        mode,
        entry: './index.tsx',
        output: {
            filename: 'task-manager.js',
            path: path.resolve(__dirname, 'build'),
            clean: true,
        },
        resolve: {
            extensions: ['.ts', '.tsx', '.js'],
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
        devtool: !isProduction ? 'source-map' : false,
        optimization: {
            minimize: isProduction,
        }
    };
};
