# Laravel Heartbeat

[![Latest Version on Packagist](https://img.shields.io/packagist/v/tahzeeb/laravel-heartbeat.svg?style=flat-square)](https://packagist.org/packages/tahzeeb/laravel-heartbeat)
[![Tests](https://img.shields.io/github/actions/workflow/status/tahzeeb536/laravel-heartbeat/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/tahzeeb536/laravel-heartbeat/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/tahzeeb/laravel-heartbeat.svg?style=flat-square)](https://packagist.org/packages/tahzeeb/laravel-heartbeat)
[![License](https://img.shields.io/packagist/l/tahzeeb/laravel-heartbeat.svg?style=flat-square)](LICENSE)

A fail-safe **cron & queue monitor** for Laravel. Install it with one line, and it automatically watches your scheduled tasks and queued jobs and pings your Laravel Heartbeat endpoint when they start, finish, or fail — so you're alerted the moment a job breaks or silently stops running.

It's a *dead man's switch* for your background work: you find out when a task **didn't** run, not just when it errored.

> 🚧 **The hosted service is launching soon.** The client package below is production-ready today. Star the repo to follow along and get notified when sign-ups open.

## Why

Most failures in a Laravel app happen where nobody's looking — a nightly command that quietly stopped firing after a deploy, or a queued job that's been failing for hours. Laravel Heartbeat closes that gap with three guarantees:

- **Zero config.** After install, every scheduled task is discovered and monitored automatically. No per-task boilerplate.
- **Catches the silent failures.** A scheduled command that exits non-zero *without throwing* still gets reported as failed — the case naive monitors miss and report as success.
- **Never breaks your app.** Every ping runs under a short timeout and swallows all errors. If the monitoring service is slow or down, your jobs run exactly as if this package weren't installed.

## Requirements

- PHP 8.2+
- Laravel 12 or 13

## Installation

Install via Composer:

```bash
composer require tahzeeb/laravel-heartbeat
```

The service provider is registered automatically. Add your project key to `.env`:

```dotenv
HEARTBEAT_API_KEY=your-project-key
```

That's it — your scheduled tasks and queued jobs are now monitored.

> **Note:** By default the package only sends pings in the `production` environment (see [Configuration](#configuration)). To try it locally, add your current environment to the `environments` array.

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=heartbeat-config
```

## How it works

Once installed, the package hooks into Laravel's own scheduler and queue events:

- **Scheduled tasks** — for every task in your schedule, it sends a `start` ping when the task begins, a `success` ping (with duration) when it finishes cleanly, and a `fail` ping if the task throws **or** exits with a non-zero code.
- **Queued jobs** — when a job fails permanently (after its retries are exhausted), it sends a `fail` ping. Failures of the same job class roll up into a single monitor, so a hundred failures show up as one red monitor, not a hundred.

Each monitor gets a stable, unique slug derived from the task or job, so it stays consistent across deploys and workers. For scheduled tasks, give a task an explicit name and the slug is derived from it instead of the raw command:

```php
$schedule->command('reports:nightly')->dailyAt('01:00')->name('nightly-reports');
```

## Manual pings

For work that isn't a scheduled task or queued job — a long-running script, a step inside a bigger job — ping Heartbeat directly with the `Heartbeat` facade:

```php
use Tahzeeb\Heartbeat\Facades\Heartbeat;

Heartbeat::start('nightly-import');

// ... do the work ...

Heartbeat::success('nightly-import', durationMs: 1250);
// or, on failure:
Heartbeat::fail('nightly-import', exitCode: 1, output: $exception->getMessage());
```

These calls are fail-safe like every other ping in the package (see [How it works](#how-it-works)) — they never throw, even if Heartbeat is unreachable or disabled.

## Configuration

All configuration is optional except `api_key`. The published `config/heartbeat.php` supports:

| Key | Env var | Default | Description |
|-----|---------|---------|-------------|
| `enabled` | `HEARTBEAT_ENABLED` | `true` | Master on/off switch for all pings. |
| `api_key` | `HEARTBEAT_API_KEY` | `null` | Your project key. If empty, the package is a no-op. |
| `url` | `HEARTBEAT_URL` | `https://api.laravelheartbeat.com/v1` | The API base URL. |
| `timeout` | `HEARTBEAT_TIMEOUT` | `2` | Connect/read timeout in seconds. Keep it small. |
| `monitor_scheduled_tasks` | — | `true` | Auto-monitor the scheduler. |
| `monitor_queue_failures` | — | `true` | Ping on permanently failed queue jobs. |
| `grace_period` | — | `60` | Default seconds of grace before a monitor is considered late. |
| `ignore` | — | `[]` | Tasks/jobs to skip (see below). |
| `environments` | — | `['production']` | Only send pings in these app environments. |

### Ignoring tasks and jobs

Add a command fragment, a monitor slug, or a job class to the `ignore` array to exclude it from monitoring entirely:

```php
'ignore' => [
    'telescope:prune',         // matches the scheduled command
    'App\\Jobs\\LogCleanup',   // matches the job class
],
```

## Commands

### `heartbeat:list`

Lists every monitor the package has discovered from your schedule, with its slug, name, cron expression, and type. Runs entirely offline — no network calls — so it's a safe way to preview exactly what will be monitored before you go live:

```bash
php artisan heartbeat:list
```

### `heartbeat:test`

Sends a single test success ping (default slug `heartbeat-test`, override with `--slug`) so you can verify your API key and connectivity. Reports the outcome plainly, including the HTTP status on failure — and never throws:

```bash
php artisan heartbeat:test
php artisan heartbeat:test --slug=my-check
```

### `heartbeat:sync`

Enumerates monitors the same way `heartbeat:list` does and POSTs them to your Heartbeat dashboard, so it always knows about every scheduled task:

```bash
php artisan heartbeat:sync
```

## Testing

```bash
composer test
```

The suite runs against Laravel 12 and 13 with no live network calls.

## Contributing

Contributions are welcome. Please make sure the tests and code style checks pass before opening a pull request:

```bash
composer test
composer format
```

## Security

If you discover a security vulnerability, please email **tahzeeb.sattar786@gmail.com** instead of opening a public issue.

## Credits

- [Tahzeeb Sattar](https://github.com/tahzeeb536)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see the [License File](LICENSE) for more information.