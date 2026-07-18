<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Tahzeeb\Heartbeat\Heartbeat;
use Tahzeeb\Heartbeat\Support\MonitorName;

final class PingOnJobFailed
{
    public function __construct(private readonly Heartbeat $heartbeat) {}

    public function handle(JobFailed $event): void
    {
        $jobClass = $event->job->resolveName();

        if (MonitorName::isQueueJobIgnored($jobClass, (array) config('heartbeat.ignore', []))) {
            return;
        }

        $this->heartbeat->fail(
            MonitorName::forQueueJob($jobClass),
            exitCode: null,
            output: $event->exception->getMessage(),
        );
    }
}
