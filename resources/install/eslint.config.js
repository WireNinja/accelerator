import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import prettier from 'eslint-config-prettier/flat';
import vue from 'eslint-plugin-vue';

export default defineConfigWithVueTs(
    vue.configs['flat/essential'],
    vueTsConfigs.recommended,
    {
        rules: {
            'vue/multi-word-component-names': 'off',
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
            'storage/**',
            'vendor/**',
            'resources/js/actions/**',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
    },
    prettier,
);
