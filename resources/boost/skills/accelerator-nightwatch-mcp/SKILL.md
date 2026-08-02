---
name: accelerator-nightwatch-mcp
description: Diagnose Laravel Nightwatch exceptions, slow operations, commands, jobs, routes, and scheduled tasks through the Nightwatch MCP. Use when a user provides a Nightwatch URL, asks to inspect Nightwatch issues or environments, rotate/reload a Nightwatch token, or verify whether the Nightwatch MCP is actually callable.
---

# Accelerator Nightwatch MCP

Use Nightwatch MCP before browser automation. A configured or enabled server in the UI is not proof that its tools are callable in the current task.

## Read-only workflow

1. Confirm `mcp__laravel_nightwatch__*` tools are exposed. If absent, report a tool-handshake problem precisely; do not claim the server is uninstalled.
2. Resolve the application UUID with `list_applications`. Never use an application name or environment UUID as `application_id`.
3. Resolve and verify the exact environment with `list_environments`.
4. Use `list_issues` with `environment_id`, `status`, and `type` when the user gives an environment or list URL.
5. Call `get_issue` for every relevant issue; fetch independent issues in parallel.
6. Group wrapper failures with their root exception. A failed scheduled task and its underlying command exception are often one incident.
7. Correlate Nightwatch evidence with source, committed topology, and narrowly scoped read-only runtime checks. Activate `accelerator-ops-observability` for VPS diagnosis.
8. Report access status, root cause, impact, evidence, and the smallest safe next action. State explicitly what was not changed.

## Mutation boundary

- Never resolve, ignore, assign, rename, comment on, or reprioritize an issue unless the user explicitly requests that exact Nightwatch mutation.
- Never print Nightwatch tokens or env contents.
- Do not use Nightwatch as proof that a fix is deployed; verify the release and runtime separately.
- Do not replace MCP with browser scraping when MCP is callable.

## Token refresh

The ignored `.accelerator/environments/{stage}.env` is authoritative. A manual edit to remote `shared/.env` is temporary and will be overwritten by the next deployment.

After an authorized remote token change, rebuild cached config and restart the exact Supervisor stage group. Do not use Laravel's generic `artisan reload`; it may be unable to signal Supervisor-owned processes.

Read [references/issue-workflow.md](references/issue-workflow.md) for tool selection, identifier rules, triage output, and token-rotation verification.
