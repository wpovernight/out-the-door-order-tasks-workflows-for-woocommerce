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
    overrides: [
        {
            // Everything outside the SDK links against it through the window
            // global, where only the barrel's exports exist.
            files: ['src/**/*.ts', 'src/**/*.tsx'],
            excludedFiles: ['src/sdk/**'],
            rules: {
                'no-restricted-imports': [
                    'error',
                    {
                        patterns: [
                            {
                                group: ['@sdk/*'],
                                message:
                                    "Import from the flat '@sdk' barrel. Webpack collapses every '@sdk/deep/path' onto window.wpo.aom.sdk, so a deep import type-checks against the source file but resolves to the barrel at runtime — a name missing from src/sdk/index.ts becomes undefined in the browser instead of a build error.",
                            },
                        ],
                    },
                ],
            },
        },
    ],
};
