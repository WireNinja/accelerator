import { existsSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import { laravelPwa } from '@wireninja/vite-plugin-laravel-pwa';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

function discoverFilamentThemes(directory) {
    if (!existsSync(directory)) {
        return [];
    }

    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            return discoverFilamentThemes(path);
        }

        return entry.name === 'theme.css' ? [path] : [];
    });
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const plugins = [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
                ...discoverFilamentThemes('resources/css/filament'),
            ],
            refresh: true,
        }),
        tailwindcss(),
    ];

    if (env.ACCELERATOR_FEATURE_PWA === 'true') {
        plugins.push(
            laravelPwa({
                name: env.VITE_APP_NAME || '{{ app_name }}',
                shortName: env.VITE_APP_NAME || '{{ app_name }}',
                description: '{{ app_name }} internal application',
                themeColor: '#18181b',
                registerType: 'prompt',
            }),
        );
    }

    return {
        plugins,
        resolve: {
            preserveSymlinks: true,
        },
        server: {
            cors: true,
            watch: {
                ignored: [
                    '**/storage/**',
                    '**/node_modules/**',
                    '**/public/build/**',
                    '**/.git/**',
                    '**/*.log',
                ],
            },
        },
    };
});
