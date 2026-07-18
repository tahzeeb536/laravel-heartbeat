<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tahzeeb\Heartbeat\Support\MonitorName;

it('syncs discovered monitors to /monitors/sync with the correct payload shape', function (): void {
    Http::fake();

    $schedule = app(Schedule::class);
    $task = $schedule->call(fn () => null)->hourly()->name('nightly-sync');
    $slug = MonitorName::forTask($task);

    $this->artisan('heartbeat:sync')
        ->assertSuccessful()
        ->expectsOutputToContain('Synced 1 monitor');

    Http::assertSent(function (Request $request) use ($slug): bool {
        if ($request->url() !== 'https://api.laravelheartbeat.com/v1/monitors/sync'
            || $request->method() !== 'POST'
            || ! $request->hasHeader('X-Heartbeat-Key', 'test-api-key')) {
            return false;
        }

        $monitors = $request['monitors'];

        return is_array($monitors)
            && count($monitors) === 1
            && $monitors[0]['slug'] === $slug
            && $monitors[0]['name'] === 'nightly-sync'
            && $monitors[0]['type'] === 'scheduled'
            && array_key_exists('cron_expression', $monitors[0])
            && array_key_exists('timezone', $monitors[0])
            && $monitors[0]['grace_period'] === 60;
    });
});

it('excludes an ignored task from the sync payload', function (): void {
    config(['heartbeat.ignore' => ['ignored-task']]);

    Http::fake();

    $schedule = app(Schedule::class);
    $schedule->call(fn () => null)->hourly()->name('ignored-task');
    $schedule->call(fn () => null)->hourly()->name('kept-task');

    $this->artisan('heartbeat:sync')->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        $monitors = $request['monitors'];

        return count($monitors) === 1 && str_starts_with((string) $monitors[0]['slug'], 'kept-task-');
    });
});

it('reports and swallows a network error during sync', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $schedule = app(Schedule::class);
    $schedule->call(fn () => null)->hourly()->name('nightly-sync');

    $this->artisan('heartbeat:sync')
        ->assertFailed()
        ->expectsOutputToContain('Failed to sync monitors');
});

it('reports when there are no monitors to sync and sends no request', function (): void {
    Http::fake();

    $this->artisan('heartbeat:sync')
        ->assertSuccessful()
        ->expectsOutputToContain('No scheduled tasks discovered');

    Http::assertNothingSent();
});
