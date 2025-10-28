module.exports = {
    root: true,
    parser: '@typescript-eslint/parser',
    extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
    plugins: [ '@typescript-eslint' ],
    parserOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
        ecmaFeatures: { jsx: true },
    },
    rules: {
        'react/react-in-jsx-scope': 'off',
        'no-console': 'off',
        '@typescript-eslint/no-unused-vars': ['warn'],
    },
};