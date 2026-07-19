# Changelog

All notable changes to `laravel-heartbeat` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-07-19

First public release — a fail-safe cron & queue monitor for Laravel.

### Added

- **Automatic scheduler discovery.** Every task in the application schedule
  (`Schedule::command()`, `::job()`, `::call()`) is monitored automatically with
  no per-task configuration.
- **Scheduled task monitoring.** Sends a `start` ping when a task begins, a
  `success` ping (with duration in milliseconds) when it finishes cleanly, and a
  `fail` ping when a task throws **or** exits with a non-zero code — catching
  silent failures that naive monitors report as success.
- **Queue failure monitoring.** Permanently failed jobs (after retries are
  exhausted) send a `fail` ping. Failures of the same job class roll up into a
  single monitor rather than one per instance.
- **Stable, deterministic slugs.** Monitor slugs are derived from a normalized,
  OS-independent command signature, so they stay consistent across deploys,
  workers, and machines. Explicit task names (`->name('...')`) are honored.
- **Manual pings** via the `Heartbeat` facade — `Heartbeat::start()`,
  `Heartbeat::success()`, and `Heartbeat::fail()` — for scripts, imports,
  deployments, and other work outside the scheduler and queue.
- **Fail-safe HTTP client.** Every ping runs under a short connect/read timeout
  and swallows all errors (offline, timeout, SSL, DNS, network, or server
  errors). If the backend is slow or down, the host application runs exactly as
  if the package were not installed. No exception ever reaches the application.
- **No-op safety gating.** The package is inert unless enabled, an API key is
  set, and the current environment is in the allowed list — checked before any
  listener is registered.
- **Artisan commands:**
  - `heartbeat:list` — lists every monitor discovered from the schedule
    (slug, name, cron, type). Runs fully offline with no network calls.
  - `heartbeat:test` — sends a single test ping to verify API key and
    connectivity, reporting the HTTP status on failure.
  - `heartbeat:sync` — enumerates discovered monitors and syncs them to the
    configured backend.
- **Configurable behavior** via `config/heartbeat.php`: `enabled`, `api_key`,
  `url`, `timeout`, `monitor_scheduled_tasks`, `monitor_queue_failures`,
  `grace_period`, `ignore`, and `environments`.
- **Backend-agnostic by design.** Points at the official service by default and
  can be redirected to any compatible backend via `HEARTBEAT_URL`.

### Requirements

- PHP 8.2+
- Laravel 12 or 13

> **Note:** This is a pre-1.0 release. The client is production-ready, but the
> wire protocol may still change before 1.0 while the hosted service is
> finalized.

[Unreleased]: https://github.com/tahzeeb536/laravel-heartbeat/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/tahzeeb536/laravel-heartbeat/releases/tag/v0.1.0