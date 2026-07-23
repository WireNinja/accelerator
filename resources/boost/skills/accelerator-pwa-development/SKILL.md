---
name: accelerator-pwa-development
description: Configure or change Accelerator Laravel PWA behavior with @wireninja/vite-plugin-laravel-pwa, including manifest values, service worker, source icon, generated assets, and Bun build verification.
---

# Accelerator PWA

Use Bun only. Reuse the installed `@wireninja/vite-plugin-laravel-pwa` and `vite-plugin-pwa`; do not copy a large raw `VitePWA` configuration unless package defaults cannot express the requirement.

## Required inputs

- Source icon: square, padded `public/favicon.svg`.
- Manifest: `name`, `shortName`, `description`, and `themeColor`.
- Optional behavior: `registerType`, colors, start URL, scope, display/orientation, explicit offline images, or low-level overrides.

Generate icons after the source exists:

```bash
bunx laravel-pwa icons
```

Expected assets include favicon, 64/192/512 icons, maskable icon, and Apple touch icon.

## Defaults to preserve

- output in `public`, service-worker scope `/`, build base `/build/`;
- hash-based public revisions;
- exclude storage/vendor/hot/git state;
- do not precache the entire Laravel public directory.

## Verification

```bash
bun run build
```

Confirm `manifest.webmanifest`, `sw.js`, Workbox output, and generated icons. If development keeps an old worker, clear the browser's service-worker/application cache before changing code again.
