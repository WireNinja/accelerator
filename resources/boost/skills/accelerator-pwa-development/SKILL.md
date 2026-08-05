---
name: accelerator-pwa-development
description: Configure Accelerator PWA manifest, icons, service worker, source icon, and Vite build with pnpm or npm.
---

# Accelerator PWA

Reuse `@wireninja/vite-plugin-laravel-pwa` and `vite-plugin-pwa`. Keep one padded square `public/favicon.svg`; configure name, short name, description, theme color, and registration behavior in Vite. Laravel Head owns document tags: Accelerator registers Filament's `@head` hook and PWA defaults; userland Blade/Livewire layouts must place `@head` inside their own `<head>`.

```bash
pnpm exec laravel-pwa icons
pnpm run build
```

For npm use `npm exec laravel-pwa icons` and `npm run build`. Do not use Bun.

Preserve output under `public`, service-worker scope `/`, build base `/build/`, hashed revisions, and exclusions for storage/vendor/hot/git state. Do not precache the entire public directory. Verify manifest, service worker, Workbox output, icons, and browser registration.
