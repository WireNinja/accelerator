---
name: accelerator-breaking-changes
description: Surgically migrate an existing Laravel or Accelerator application to v2. Never run the fresh installer.
---

# Accelerator existing-app migration

Record current routes, panels/resources, schema, schedules, feature env, lockfiles, deployment topology, and user auth fields. Back up data before schema changes. Keep application domain code unless a removed Accelerator subsystem owns it.

Target contract:

- pnpm default/npm fallback; one lockfile and supply-chain policy;
- Filament native auth/MFA; remove Fortify duplicate fields/services;
- root `/` remains app-owned;
- remove Inertia/Vue/Wayfinder only when the app does not independently need them;
- remove ticketing/custom telemetry/Insider/Envoy/Bun remnants;
- move LocationPicker imports to the package component;
- retain small `BetterEnum` where domain code uses it;
- use `null = all` and `[] = none` for role default permissions;
- app-owned `NavigationGroup`; resource icon/label/policy stay local;
- committed `.easyploy/manifest.json`, ignored Easyploy stage envs, and Easyploy as the sole deployment control plane.

Never rewrite an applied migration blindly. Add an explicit transition migration for existing data. Verify boot, caches, route topology, PHPStan level 5, Composer audit/validate, pnpm/npm peers/build, RBAC, and affected UI. No compatibility facade or dual old/new workflow remains at completion.
