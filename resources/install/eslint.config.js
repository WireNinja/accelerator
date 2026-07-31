import prettier from 'eslint-config-prettier/flat';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    ...tseslint.configs.recommended,
    {
        rules: {
            '@typescript-eslint/consistent-type-imports': [
                'error',
                {
                    prefer: 'type-imports',
                    fixStyle: 'separate-type-imports',
                },
            ],
        },
    },
    {
        ignores: [
            '.accelerator/**',
            'bootstrap/cache/**',
            'bootstrap/ssr/**',
            'node_modules/**',
            'public/**',
            'resources/vendor/**',
            'storage/**',
            'vendor/**',
        ],
    },
    prettier,
);
