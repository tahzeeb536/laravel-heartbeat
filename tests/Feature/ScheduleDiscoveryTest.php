<?php

declare(strict_types=1);

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tahzeeb\Heartbeat\Data\Monitor;
use Tahzeeb\Heartbeat\Scheduling\ScheduleInspector;
use Tahzeeb\Heartbeat\Support\MonitorName;

it('enumerates scheduled tasks into monitors, never watching nothing', function (): void {
    $schedule = app(Schedule::class);
    $inspireTask = $schedule->command('inspire')->daily();
    $callbackTask = $schedule->call(fn () => null)->hourly()->name('nightly-sync');

    $monitors = app(ScheduleInspector::class)->monitors();

    expect(count($monitors))->toBeGreaterThan(0)
        ->and($monitors)->toHaveCount(2);

    $bySlug = collect($monitors)->keyBy(fn (Monitor $monitor): string => $monitor->slug);

    $inspireSlug = MonitorName::forTask($inspireTask);
    $callbackSlug = MonitorName::forTask($callbackTask);

    expect($bySlug)->toHaveKey($inspireSlug)
        ->and($bySlug)->toHaveKey($callbackSlug)
        ->and($callbackSlug)->toStartWith('nightly-sync-')
        ->and($callbackSlug)->toMatch('/^[a-z0-9-]+$/');

    expect($bySlug[$inspireSlug]->type)->toBe('scheduled')
        ->and($bySlug[$inspireSlug]->cronExpression)->toBe($inspireTask->expression)
        ->and($bySlug[$callbackSlug]->type)->toBe('scheduled')
        ->and($bySlug[$callbackSlug]->cronExpression)->toBe($callbackTask->expression);
});

it('shows a defined task slug in heartbeat:list output', function (): void {
    $schedule = app(Schedule::class);
    $schedule->call(fn () => null)->hourly()->name('nightly-sync');

    $this->artisan('heartbeat:list')
        ->assertSuccessful()
        ->expectsOutputToContain('nightly-sync');
});

it('derives a stable, url-safe slug for the same task', function (): void {
    $schedule = app(Schedule::class);
    $task = $schedule->command('inspire')->daily();

    $first = MonitorName::forTask($task);
    $second = MonitorName::forTask($task);

    expect($first)->toBe($second)
        ->and($first)->toMatch('/^[a-z0-9-]+$/');
});

it('sends a start ping when a scheduled task starts', function (): void {
    Http::fake();

    $schedule = app(Schedule::class);
    $task = $schedule->call(fn () => null)->hourly()->name('nightly-sync');
    $slug = MonitorName::forTask($task);

    event(new ScheduledTaskStarting($task));

    Http::assertSent(function (Request $request) use ($slug): bool {
        return $request->url() === "https://api.laravelheartbeat.com/v1/ping/{$slug}/start"
            && str_starts_with($slug, 'nightly-sync-')
            && preg_match('/^[a-z0-9-]+$/', $slug) === 1
            && $request->method() === 'POST';
    });
});

it('sends a success ping with duration_ms when a scheduled task finishes cleanly', function (): void {
    Http::fake();

    $schedule = app(Schedule::class);
    $task = $schedule->call(fn () => null)->hourly()->name('nightly-sync');
    $task->exitCode = 0;
    $slug = MonitorName::forTask($task);

    event(new ScheduledTaskFinished($task, 1.5));

    Http::assertSent(function (Request $request) use ($slug): bool {
        return $request->url() === "https://api.laravelheartbeat.com/v1/ping/{$slug}"
            && str_starts_with($slug, 'nightly-sync-')
            && $request['duration_ms'] === 1500;
    });
});

it('sends a fail ping when a scheduled task finishes with a non-zero exit code', function (): void {
    Http::fake();

    $schedule = app(Schedule::class);
    $task = $schedule->call(fn () => null)->hourly()->name('nightly-sync');
    $task->exitCode = 1;
    $slug = MonitorName::forTask($task);

    event(new ScheduledTaskFinished($task, 0.5));

    Http::assertSent(function (Request $request) use ($slug): bool {
        return $request->url() === "https://api.laravelheartbeat.com/v1/ping/{$slug}/fail"
            && str_starts_with($slug, 'nightly-sync-')
            && $request['exit_code'] === 1;
    });

    Http::assertNotSent(fn (Request $request): bool => $request->url() === "https://api.laravelheartbeat.com/v1/ping/{$slug}");
});

it('sends a fail ping with the exception message when a scheduled task throws', function (): void {
    Http::fake();

    $schedule = app(Schedule::class);
    $task = $schedule->call(fn () => null)->hourly()->name('nightly-sync');
    $slug = MonitorName::forTask($task);

    event(new ScheduledTaskFailed($task, new Exception('boom')));

    Http::assertSent(function (Request $request) use ($slug): bool {
        return $request->url() === "https://api.laravelheartbeat.com/v1/ping/{$slug}/fail"
            && str_contains((string) $request['output'], 'boom');
    });
});

it('does not ping or list an ignored task', function (): void {
    config(['heartbeat.ignore' => ['ignored-task']]);

    Http::fake();

    $schedule = app(Schedule::class);
    $task = $schedule->call(fn () => null)->hourly()->name('ignored-task');

    event(new ScheduledTaskStarting($task));

    Http::assertNothingSent();

    $monitors = app(ScheduleInspector::class)->monitors();

    expect($monitors)->toBeEmpty();
});

it('uses the same slug at runtime as ScheduleInspector lists for the same task', function (): void {
    Http::fake();

    $schedule = app(Schedule::class);
    $task = $schedule->call(fn () => null)->hourly()->name('nightly-sync');

    $listedSlug = collect(app(ScheduleInspector::class)->monitors())->first()->slug;

    event(new ScheduledTaskStarting($task));

    Http::assertSent(fn (Request $request): bool => $request->url() === "https://api.laravelheartbeat.com/v1/ping/{$listedSlug}/start");
});

it('enumerates a task with a DateTimeZone timezone object without a fatal error', function (): void {
    $schedule = app(Schedule::class);
    $schedule->call(fn () => null)->hourly()->name('utc-task')->timezone(new DateTimeZone('UTC'));

    $monitors = app(ScheduleInspector::class)->monitors();

    $monitor = collect($monitors)->first(fn (Monitor $monitor): bool => str_starts_with($monitor->slug, 'utc-task-'));

    expect($monitor)->not->toBeNull()
        ->and($monitor->timezone)->toBe('UTC');
});

it('enumerates a task with a string timezone', function (): void {
    $schedule = app(Schedule::class);
    $schedule->call(fn () => null)->hourly()->name('london-task')->timezone('Europe/London');

    $monitors = app(ScheduleInspector::class)->monitors();

    $monitor = collect($monitors)->first(fn (Monitor $monitor): bool => str_starts_with($monitor->slug, 'london-task-'));

    expect($monitor)->not->toBeNull()
        ->and($monitor->timezone)->toBe('Europe/London');
});

it('derives a platform-independent slug and display name for a command task with no description', function (): void {
    $schedule = app(Schedule::class);
    $task = $schedule->command('inspire')->everyMinute();

    $slug = MonitorName::forTask($task);
    $name = MonitorName::nameForTask($task);

    expect($slug)->toMatch('/^inspire-[0-9a-f]{8}$/')
        ->and($slug)->not->toContain('php')
        ->and($slug)->not->toContain('exe')
        ->and($slug)->not->toContain('nul')
        ->and($name)->toBe('inspire')
        ->and($name)->not->toContain('php')
        ->and($name)->not->toContain('exe')
        ->and($name)->not->toContain('nul')
        ->and($name)->not->toContain('2>&1');

    $monitor = collect(app(ScheduleInspector::class)->monitors())->first(fn (Monitor $monitor): bool => $monitor->slug === $slug);

    expect($monitor)->not->toBeNull()
        ->and($monitor->name)->toBe('inspire');

    $this->artisan('heartbeat:list')
        ->assertSuccessful()
        ->expectsOutputToContain('inspire')
        ->doesntExpectOutputToContain('php.exe');
});

it('gives two unnamed closure tasks distinct, stable slugs', function (): void {
    $schedule = app(Schedule::class);
    $first = $schedule->call(fn () => 'first')->hourly();
    $second = $schedule->call(fn () => 'second')->daily();

    $monitors = app(ScheduleInspector::class)->monitors();

    expect($monitors)->toHaveCount(2);

    $firstSlug = MonitorName::forTask($first);
    $secondSlug = MonitorName::forTask($second);

    expect($firstSlug)->not->toBe($secondSlug);

    $firstSlugAgain = MonitorName::forTask($first);

    expect($firstSlug)->toBe($firstSlugAgain);
});
