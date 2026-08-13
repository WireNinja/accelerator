---
name: accelerator-observability
description: Configure, inspect, or troubleshoot Accelerator OpenTelemetry export to centralized self-hosted OpenObserve. Use for OTLP credentials, authenticated-only HTTP tracing/logging, service identity, traces, metrics, logs, sampling, or observability failures.
---

# Accelerator observability

Use `keepsuit/laravel-opentelemetry`; do not install Nightwatch, NightOwl, Sentry, an agent daemon, a telemetry database, or an OpenTelemetry Collector in a client project.

## Contract

- OpenObserve is host infrastructure outside Accelerator deployment ownership.
- Each `{deployment_key}:{stage}` has a distinct ingestion-only credential.
- Set both `OTEL_SERVICE_NAME` and `OTEL_SERVICE_INSTANCE_ID` to `{deployment_key}-{stage}` so OpenObserve shows each runtime as one unambiguous service.
- Include `service.namespace=accelerator` and `deployment.environment.name={stage}` in `OTEL_RESOURCE_ATTRIBUTES`.
- Keep `OTEL_INSTRUMENTATION_HTTP_SERVER=false`. Accelerator's web middleware starts traces only after an authenticated user is available.
- Guest HTTP requests and their logs must not reach OTLP. CLI, scheduled work, and queue jobs remain observable.
- Default trace sampling is `1.0`; tune only through env after measuring volume.
- Never print or commit `OTEL_EXPORTER_OTLP_HEADERS`.

## Workflow

1. Inspect package version and `config('accelerator.features.observability')`.
2. Inspect keys only—never dump a whole stage env.
3. Validate endpoint, protocol, resource identity, HTTP instrumentation disabled, and a non-empty header.
4. Clear cached configuration after an env change.
5. For local diagnosis, use `OTEL_SDK_DISABLED=true` when no valid ingestion credential is configured.
6. Verify one authenticated request, one queued job, one selected command, one log, and one metric in OpenObserve. Verify a guest request produces no OTLP data.

OpenObserve Service Catalog's **All** count is not the number of Laravel applications. It also includes inferred datastore nodes. The intended application identity is the entry classified as `service`; MySQL commonly appears as a `database` node named from `db.namespace`, while Redis can appear as a `database` node named by its configured DB index such as `1`. These are valid OpenTelemetry semantic-convention attributes, not leaked or legacy `service.name` values. Verify `service_name` and `infer_service_type` before diagnosing an identity collision.

OpenObserve retention is 60 days. Changing backend retention, users, storage, Nginx, or systemd is a separate host-infrastructure task and requires explicit authorization.
