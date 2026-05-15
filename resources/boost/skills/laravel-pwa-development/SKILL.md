---
name: laravel-pwa-development
description: Set up and maintain Laravel PWAs with @wireninja/vite-plugin-laravel-pwa, including Vite config, required manifest values, icon source files, CLI asset generation, and build verification.
---

# Laravel PWA Development

## When To Use

Use this skill when setting up or changing a Laravel PWA that uses `@wireninja/vite-plugin-laravel-pwa`, `vite-plugin-pwa`, service workers, web app manifests, or generated PWA icons.

## Install

Install the package with `vite-plugin-pwa`:

```bash
npm install -D @wireninja/vite-plugin-laravel-pwa vite-plugin-pwa
```

For Bun projects:

```bash
bun add -d @wireninja/vite-plugin-laravel-pwa vite-plugin-pwa
```

## Required Source Icon

Prepare one source SVG:

```text
public/favicon.svg
```

Use a square, centered logo with enough safe padding. This source is used to generate the opinionated Laravel public icon set.

## Generate Assets

Use the package CLI:

```bash
npx laravel-pwa icons
```

It defaults to:

```text
public/favicon.svg
```

Generated files:

```text
public/favicon.ico
public/pwa-64x64.png
public/pwa-192x192.png
public/pwa-512x512.png
public/maskable-icon-512x512.png
public/apple-touch-icon-180x180.png
```

To use a different SVG:

```bash
npx laravel-pwa icons --source=public/logo.svg --preset=minimal
```

## Vite Config

Import and use the plugin in `vite.config.js`:

```js
import { laravelPwa } from '@wireninja/vite-plugin-laravel-pwa';

laravelPwa({
    name: 'Application Name',
    shortName: 'App',
    description: 'Short application description',
    themeColor: '#111827',
});
```

Required values:

- `name`
- `shortName`
- `description`
- `themeColor`

`backgroundColor` defaults to `themeColor`.

Common optional values:

- `registerType`: use `prompt` for controlled updates or `autoUpdate` for immediate updates
- `backgroundColor`
- `startUrl`
- `scope`
- `id`
- `orientation`
- `display`
- `additionalImages`: explicit public images that should be available offline
- `manifest`: low-level manifest overrides
- `pwa`: low-level `vite-plugin-pwa` overrides

## Defaults

The package is intentionally opinionated for Laravel:

- `outDir` is `public`
- `buildBase` is `/build/`
- service worker scope is `/`
- default icons match the generated filenames
- public asset revisions use file hashes, not `Date.now()`
- `public/storage/**`, `vendor/**`, `hot`, and `.git/**` are ignored by Workbox
- `includeAssets` stays empty to avoid caching the whole Laravel public directory with bad `/build` prefixes

Do not manually copy the old large `VitePWA(...)` config into new projects unless the package defaults are insufficient.

## Verification

After setup or changes:

```bash
bun run build
```

Confirm build output includes:

```text
public/manifest.webmanifest
public/sw.js
public/workbox-*.js
```

If a browser does not pick up a new PWA version, clear the service worker/application cache in dev tools or change the manifest-visible config.
