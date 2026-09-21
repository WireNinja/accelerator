# Changelog

All notable Accelerator changes are recorded here. Entries describe released or pending package
behavior, not abandoned local experiments.

## Unreleased

## 2.2.3 - 2026-09-22

### Added

- Expose Accelerator's resolved local backup directory in the private restore context so deployment
  tooling can stage a verified off-host archive without guessing Laravel filesystem paths.

### Fixed

- Treat missing operator Telegram credentials as an unconfigured notifier instead of throwing
  while handling an operational failure.
