---
name: echo-development
description: Develop realtime broadcasting with Laravel Echo in an Accelerator application using the centralized Reverb service. Use for broadcast events, channels, authorization, Echo listeners, presence, whispers, model broadcasting, and realtime troubleshooting.
---

# Accelerator broadcasting and Echo

Use version-specific Laravel documentation through `search-docs` before changing broadcasting behavior. Follow installed sibling conventions and keep channel authorization server-side.

## Runtime contract

- The application is a client of centralized Reverb. Never run, configure, supervise, or proxy a local `reverb:start` process.
- Every local, staging, and production runtime owns a distinct Reverb app ID, key, secret, and allowed origin. Never share an app key across stages.
- `.accelerator/reverb-apps.json` is the ignored secret registration payload for the centralized server. Import changed entries centrally before deploying or using rotated client credentials.
- Queued broadcasts use the database queue drained by Accelerator's bounded sub-minute scheduler. Never add Horizon, Supervisor, or a persistent `queue:work` process.
- Use pnpm by default and npm only when the project is explicitly configured for it. Never introduce a second lockfile.

## Implementation workflow

1. Inspect `config/broadcasting.php`, `config/reverb.php`, `routes/channels.php`, and the existing Echo bootstrap before editing.
2. Create broadcast events with native Laravel interfaces. Prefer `ShouldBroadcast` for normal delivery, `ShouldBroadcastNow` only when synchronous failure and latency are intentional, and after-commit dispatch when listeners depend on committed database state.
3. Authorize every private or presence channel against the authenticated user. Filament visibility is not authorization.
4. Keep payloads explicit with `broadcastWith()` when models contain fields that clients do not need.
5. Listen through the existing Echo client; do not reinstall broadcasting scaffolding blindly.
6. Verify `php artisan channel:list`, stage env validation, the scheduled queue drain, and a websocket connection using that runtime's own app key.

## Common constraints

- `BROADCAST_CONNECTION=reverb` selects the Laravel connection.
- `VITE_REVERB_APP_KEY` must match that runtime's `REVERB_APP_KEY`.
- Public host, port, and scheme must point to centralized Reverb, not the client domain.
- `broadcastAs()` requires a leading dot in the Echo listener name.
- `toOthers()` requires `InteractsWithSockets` and the `X-Socket-ID` header.
- Presence authorization returns user data, not a boolean.
- Code rollback never rotates or rewrites Reverb credentials.

For environment changes, use `accelerator-env-config`. For read-only diagnosis, use `accelerator-ops-observability`. Do not mutate the centralized Reverb checkout as an implicit part of a client deployment.
