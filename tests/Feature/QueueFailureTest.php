<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Http;
use Tahzeeb\Heartbeat\Support\MonitorName;

it('sends a fail ping with output containing the exception message and a null exit code', function (): void {
    Http::fake();

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\FailingTestJob');
    $job->shouldReceive('getConnectionName')->andReturn('redis');

    event(new JobFailed('redis', $job, new Exception('kaboom')));

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        $slug = MonitorName::forQueueJob('App\\Jobs\\FailingTestJob');

        return $request->url() === "https://api.laravelheartbeat.com/v1/ping/{$slug}/fail"
            && $request->method() === 'POST'
            && str_contains((string) $request['output'], 'kaboom')
            && $request['exit_code'] === null;
    });
});

it('rolls up two failures of the same job class into the same slug', function (): void {
    Http::fake();

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\FailingTestJob');
    $job->shouldReceive('getConnectionName')->andReturn('redis');

    event(new JobFailed('redis', $job, new Exception('first failure')));
    event(new JobFailed('redis', $job, new Exception('second failure')));

    $slug = MonitorName::forQueueJob('App\\Jobs\\FailingTestJob');

    expect($slug)->toMatch('/^[a-z0-9-]+$/');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->url() === "https://api.laravelheartbeat.com/v1/ping/{$slug}/fail");

    $urls = collect(Http::recorded())
        ->map(fn (array $pair): string => $pair[0]->url())
        ->unique();

    expect($urls)->toHaveCount(1);
});

it('gives a different job class a different slug', function (): void {
    Http::fake();

    $jobOne = Mockery::mock(Job::class);
    $jobOne->shouldReceive('resolveName')->andReturn('App\\Jobs\\FailingTestJob');
    $jobOne->shouldReceive('getConnectionName')->andReturn('redis');

    $jobTwo = Mockery::mock(Job::class);
    $jobTwo->shouldReceive('resolveName')->andReturn('App\\Jobs\\AnotherTestJob');
    $jobTwo->shouldReceive('getConnectionName')->andReturn('redis');

    event(new JobFailed('redis', $jobOne, new Exception('boom one')));
    event(new JobFailed('redis', $jobTwo, new Exception('boom two')));

    $slugOne = MonitorName::forQueueJob('App\\Jobs\\FailingTestJob');
    $slugTwo = MonitorName::forQueueJob('App\\Jobs\\AnotherTestJob');

    expect($slugOne)->not->toBe($slugTwo);

    Http::assertSent(fn (Request $request): bool => $request->url() === "https://api.laravelheartbeat.com/v1/ping/{$slugOne}/fail");
    Http::assertSent(fn (Request $request): bool => $request->url() === "https://api.laravelheartbeat.com/v1/ping/{$slugTwo}/fail");
});

it('does not ping when the failed job class is ignored', function (): void {
    config(['heartbeat.ignore' => [MonitorName::forQueueJob('App\\Jobs\\IgnoredJob')]]);

    Http::fake();

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\IgnoredJob');
    $job->shouldReceive('getConnectionName')->andReturn('redis');

    event(new JobFailed('redis', $job, new Exception('should be ignored')));

    Http::assertNothingSent();
});

it('does not throw when the exception message is empty or the job name is odd', function (): void {
    Http::fake();

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('');
    $job->shouldReceive('getConnectionName')->andReturn('redis');

    expect(fn () => event(new JobFailed('redis', $job, new Exception(''))))->not->toThrow(Throwable::class);

    Http::assertSent(function (Request $request): bool {
        $slug = MonitorName::forQueueJob('');

        return $request->url() === "https://api.laravelheartbeat.com/v1/ping/{$slug}/fail"
            && preg_match('/^[a-z0-9-]+$/', $slug) === 1;
    });
});
