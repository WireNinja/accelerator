# Nightwatch MCP issue workflow

## Tool map

| Goal | Tool | Notes |
|---|---|---|
| Find application UUID | `list_applications` | Search by name, then list all if no exact result. |
| Map application environments | `list_environments` | Match the user-provided environment UUID exactly. |
| Discover/prioritize issues | `list_issues` | Filter by environment, open/resolved/ignored status, and issue type. |
| Read diagnostics | `get_issue` | Retrieve stack trace, context, timestamps, counts, and activity. |
| Add discussion | `add_issue_comment` | Side effect; explicit authorization required. |
| Change issue | `update_issue` | Side effect; explicit authorization required. |

## Identifier rules

- `application_id` is always the UUID returned by `list_applications`.
- `environment_id` is always the UUID returned by `list_environments`.
- A full issue URL may be passed directly to `get_issue`.
- An environment/list URL does not identify one issue. Resolve its application, then call `list_issues`.
- A numeric issue reference requires `application_id`; include `environment_id` to force diagnostics from the intended stage.

## Efficient triage

1. List applications once and remember the resolved UUID for the task.
2. List environments once and match the exact stage.
3. List only the requested status/type.
4. Fetch all relevant issue details in parallel.
5. Order the report by causal chain, not Nightwatch issue number.

Example causal grouping:

```text
root exception: ZipArchive permission denied
        |
        +--> command backup:run exits 1
                    |
                    +--> scheduler reports task failed
```

Treat this as one incident with multiple observations unless evidence shows independent causes.

## Required report

- MCP access: callable or unavailable, with the exact boundary.
- Application and environment resolved.
- Issue refs, first/last seen, occurrence count, and affected users.
- Root cause and duplicate/wrapper relationship.
- Source/runtime evidence used to confirm the cause.
- Recommended action, separating immediate recovery from permanent source fixes.
- Explicit mutation statement: comments/status/runtime/source unchanged unless authorized.

## Token rotation and cached config

Persist the token in the local ignored stage file first:

```text
.accelerator/environments/{stage}.env
```

Normal durable path:

```bash
php artisan accelerator:deploy --stage={stage}
```

For an explicitly authorized env-only remote refresh, resolve the stage group from `.accelerator/deploy.json`, then run equivalent operations on that stage only:

```text
php artisan config:cache
sudo supervisorctl restart "{service_group}:*"
```

Restarting only the Nightwatch agent is insufficient when Octane, Horizon, queue workers, or the scheduler also booted with stale Laravel config. Never expose the token while comparing local and remote state; compare presence or a non-reversible fingerprint only when necessary.
