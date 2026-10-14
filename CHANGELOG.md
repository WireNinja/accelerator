# Changelog

All notable Accelerator changes are recorded here. Entries describe released or pending package
behavior, not abandoned local experiments.

## Unreleased

## 2.2.5 - 2026-10-14

### Fixed

- Default DiceBear avatars and fresh installation environments to API v10, matching the hosted
  service. Requests to the unsupported v9 route on that service returned JSON errors instead of SVG images and triggered
  browser opaque response blocking.

## 2.2.4 - 2026-09-26

### Fixed

- Allow a provisioned account with no existing Google identity link to complete its first Google
  OAuth login in `existing_only` mode.

## 2.2.3 - 2026-09-22

### Added

- Expose Accelerator's resolved local backup directory in the private restore context so deployment
  tooling can stage a verified off-host archive without guessing Laravel filesystem paths.

### Fixed

- Treat missing operator Telegram credentials as an unconfigured notifier instead of throwing
  while handling an operational failure.
