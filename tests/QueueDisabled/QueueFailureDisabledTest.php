<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Http;

it('does not register the queue failure listener when monitor_queue_failures is false', function (): void {
    Http::fake();

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\FailingTestJob');
    $job->shouldReceive('getConnectionName')->andReturn('redis');

    event(new JobFailed('redis', $job, new Exception('kaboom')));

    Http::assertNothingSent();
});
