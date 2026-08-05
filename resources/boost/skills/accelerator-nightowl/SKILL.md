---
name: accelerator-nightowl
description: Configure, deploy, inspect, or troubleshoot Accelerator self-hosted NightOwl observability, its underlying Laravel Nightwatch instrumentation, authenticated-user-only sampling, per-stage PostgreSQL identity, agent ports, schema migrations, Supervisor service, or local env refreshes. Use whenever NightOwl, Nightwatch telemetry, observability ingestion, or the NightOwl agent is mentioned.
---

# Accelerator NightOwl

Treat NightOwl as Accelerator's only observability destination. Laravel Nightwatch is an internal instrumentation dependency, never a second hosted destination.

## Invariants

- Capture authenticated web requests at `NIGHTOWL_AUTHENTICATED_REQUEST_SAMPLE_RATE` (default `1.0`). Keep global Nightwatch request and exception rates at `0.0`; middleware enables the trace only after session authentication resolves.
- Capture commands, scheduled tasks, jobs, queries, outgoing requests, mail, notifications, and cache events at full fidelity by default.
- Never enable `NIGHTOWL_PARALLEL_WITH_NIGHTWATCH`.
- Give every `{deployment_key}:{stage}` its own PostgreSQL role and database named `acc_nightowl_{deployment_key}_{stage}`. Never share observability databases across apps or stages.
- Keep NightOwl on loopback. Reserve stage offsets `+2` TCP ingest, `+3` UDP, and `+4` health; UDP is disabled by default.
- Do not mutate Grafana or any external NightOwl infrastructure unless the user explicitly includes it.
- Never print database passwords or entire env files.

## Workflow

1. Read `.accelerator/deploy.json` and the selected ignored `.accelerator/environments/{stage}.env` through redacted tooling.
2. Use `php artisan accelerator:env:validate --stage={stage}` before remote mutation.
3. Use `php artisan accelerator:deploy:init --stage={stage}` for first NightOwl provisioning or migration from the old managed Nightwatch agent. It creates/updates the deterministic PostgreSQL role, creates the database, runs `nightowl:install` once, and replaces the old Supervisor process.
4. Use `php artisan accelerator:deploy --stage={stage}` afterward; every release runs `nightowl:migrate` idempotently.
5. Use `php artisan accelerator:service:{status|restart} nightowl --stage={stage}` and `accelerator:logs nightowl` for agent operations.
6. After an env-only change, use `accelerator:env:push`; it clears cached configuration and restarts the whole stage group because Octane, workers, scheduler, and agent all retain boot-time config.
7. Verify the release health, Supervisor state, NightOwl health port, and recent agent log. Do not infer successful ingestion from an HTTP application health check alone.

Read [references/operations.md](references/operations.md) before database provisioning, agent migration, or incident diagnosis.
