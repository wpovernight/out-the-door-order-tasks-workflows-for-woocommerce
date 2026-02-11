module.exports = {
    root: true,
    parser: '@typescript-eslint/parser',
    extends: [
        'plugin:@wordpress/eslint-plugin/recommended',
        // 'plugin:@typescript-eslint/recommended',
        'plugin:import/typescript',
    ],
    parserOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
        ecmaFeatures: {jsx: true},
        project: './tsconfig.json',
    },
    settings: {
        'import/resolver': {
            typescript: {
                alwaysTryTypes: true,
            },
        },
    },
    rules: {
        'no-console': 'off', // Temporarily allow console statements
        '@typescript-eslint/no-unused-vars': 'off', // Temporarily allow unused variables
    },
};
